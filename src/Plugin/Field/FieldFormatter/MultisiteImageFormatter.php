<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StreamWrapper\LocalStream;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_api_base\Environment\EnvironmentResolverException;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\responsive_image\Entity\ResponsiveImageStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders a relative image path from the current or another instance.
 */
#[FieldFormatter(
  id: 'multisite_image',
  label: new TranslatableMarkup('Multisite image'),
  field_types: [
    'string',
    'string_long',
    'text',
    'text_long',
  ],
)]
final class MultisiteImageFormatter extends FormatterBase {

  /**
   * The environment resolver.
   */
  private EnvironmentResolverInterface $environmentResolver;

  /**
   * The config factory.
   */
  private ConfigFactoryInterface $configFactory;

  /**
   * The stream wrapper manager.
   */
  private StreamWrapperManagerInterface $streamWrapperManager;

  /**
   * The responsive image style storage.
   */
  private EntityStorageInterface $responsiveImageStyleStorage;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) : self {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->environmentResolver = $container->get(EnvironmentResolverInterface::class);
    $instance->configFactory = $container->get(ConfigFactoryInterface::class);
    $instance->streamWrapperManager = $container->get(StreamWrapperManagerInterface::class);
    $instance->responsiveImageStyleStorage = $container->get('entity_type.manager')->getStorage('responsive_image_style');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) : bool {
    $module_handler = \Drupal::moduleHandler();
    return $module_handler->moduleExists('responsive_image')
      && $module_handler->moduleExists('imagecache_external');
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() : array {
    return [
      'responsive_image_style' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) : array {
    $elements = parent::settingsForm($form, $form_state);
    $options = [];
    $styles = $this->responsiveImageStyleStorage->loadMultiple();
    uasort($styles, [ResponsiveImageStyle::class, 'sort']);
    foreach ($styles as $machine_name => $style) {
      assert($style instanceof ResponsiveImageStyle);
      if ($style->hasImageStyleMappings()) {
        $options[$machine_name] = $style->label();
      }
    }

    $elements['responsive_image_style'] = [
      '#title' => $this->t('Responsive image style'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('responsive_image_style') ?: NULL,
      '#required' => TRUE,
      '#options' => $options,
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() : array {
    $style = $this->responsiveImageStyleStorage->load($this->getSetting('responsive_image_style'));
    if ($style) {
      return [
        $this->t('Responsive image style: @style', ['@style' => $style->label()]),
      ];
    }
    return [$this->t('Select a responsive image style.')];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) : array {
    $elements = [];
    $instance = $this->getInstance($items);
    if ($instance === NULL) {
      return $elements;
    }

    try {
      $current = $this->environmentResolver->getActiveProject()->getName();
    }
    catch (EnvironmentResolverException) {
      return $elements;
    }

    foreach ($items as $delta => $item) {
      $path = is_string($item->value ?? NULL) ? $item->value : '';
      if ($path === '') {
        continue;
      }

      $public_uri = $this->toPublicUri($path);
      if ($instance === $current && $public_uri && file_exists($public_uri)) {
        $elements[$delta] = $this->buildImage('responsive_image', $public_uri);
        continue;
      }

      $uri = UrlHelper::isExternal($path) ? $path : $this->toAbsoluteUrl($instance, $path);
      if ($uri === NULL) {
        continue;
      }
      $elements[$delta] = $this->buildImage('imagecache_external_responsive', $uri);
    }

    return $elements;
  }

  /**
   * Reads the parent entity's instance name.
   */
  private function getInstance(FieldItemListInterface $items) : ?string {
    $entity = $items->getEntity();
    if (!$entity->hasField('instance')) {
      return NULL;
    }
    $instance = $entity->get('instance')->value ?? NULL;
    return is_string($instance) && $instance !== '' ? $instance : NULL;
  }

  /**
   * Converts a proxied public file path into a public:// URI.
   */
  private function toPublicUri(string $path) : ?string {
    if (str_starts_with($path, 'public://')) {
      return $path;
    }

    $path = ltrim($path, '/');
    $asset_path = $this->configFactory->get('helfi_proxy.settings')->get('asset_path');
    if (is_string($asset_path) && $asset_path !== '' && str_starts_with($path, $asset_path . '/')) {
      $path = substr($path, strlen($asset_path) + 1);
    }

    $prefix = $this->publicFilesDirectory() . '/';
    if (!str_starts_with($path, $prefix)) {
      return NULL;
    }
    return 'public://' . substr($path, strlen($prefix));
  }

  /**
   * Gets the public files directory used in file URLs.
   */
  private function publicFilesDirectory() : string {
    $wrapper = $this->streamWrapperManager->getViaScheme('public');
    if ($wrapper instanceof LocalStream) {
      $directory = trim($wrapper->getDirectoryPath(), '/');
      if ($directory !== '') {
        return $directory;
      }
    }
    return 'sites/default/files';
  }

  /**
   * Prefixes a relative path with the instance's internal base URL.
   *
   * @return string|null
   *   The absolute URL, or NULL when the instance is unknown.
   */
  private function toAbsoluteUrl(string $instance, string $path) : ?string {
    try {
      $environment = $this->environmentResolver->getEnvironment(
        $instance,
        $this->environmentResolver->getActiveEnvironmentName(),
      );
    }
    catch (EnvironmentResolverException) {
      return NULL;
    }
    return rtrim($environment->getInternalBaseUrl(), '/') . '/' . ltrim($path, '/');
  }

  /**
   * Builds a responsive image render element.
   */
  private function buildImage(string $theme, string $uri) : array {
    return [
      '#theme' => $theme,
      '#uri' => $uri,
      '#responsive_image_style_id' => $this->getSetting('responsive_image_style'),
      '#alt' => '',
      '#attributes' => [
        'alt' => '',
      ],
    ];
  }

}
