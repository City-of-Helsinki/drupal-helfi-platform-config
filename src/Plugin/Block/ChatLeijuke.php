<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\Block;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Provides a Chat Leijuke block.
 */
#[Block(
  id: "chat_leijuke",
  admin_label: new TranslatableMarkup('Chat Leijuke'),
)]
final class ChatLeijuke extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a Chat Leijuke Block object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Extension\ModuleExtensionList $moduleList
   *   The module extension list.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected ModuleExtensionList $moduleList,
    protected ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('extension.list.module'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();

    $form['chat_selection'] = [
      '#type' => 'select',
      '#title' => $this->t('Chat/bot provider'),
      '#description' => $this->t('Choose the approriate chat/bot provider?'),
      '#default_value' => $config['chat_selection'] ?? '',
      '#options' => [
        'genesys_suunte' => $this->t('Genesys SUUNTE'),
      ],
    ];

    $form['chat_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Chat title'),
      '#default_value' => $config['chat_title'] ?? '',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['chat_selection'] = $form_state->getValue('chat_selection');
    $this->configuration['chat_title'] = $form_state->getValue('chat_title');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $library = ['helfi_platform_config/chat_leijuke'];
    $config = $this->getConfiguration();
    $build = [];
    $chat_library = [];
    $module_path = $this->moduleList->getPath('helfi_platform_config');
    $asset_path = $this->configFactory->get('helfi_proxy.settings')->get('asset_path');
    $libraries_yaml = Yaml::parseFile($module_path . '/helfi_platform_config.libraries.yml');

    foreach ($libraries_yaml as $library_name => $library_configuration) {
      if ($library_name !== $config['chat_selection']) {
        continue;
      }

      if (array_key_exists('js', $library_configuration)) {
        foreach ($library_configuration['js'] as $key => $value) {
          $js = [
            'url' => $key,
            'ext' => $value['type'] ?? FALSE,
            'onload' => $value['attributes']['onload'] ?? FALSE,
            'async' => $value['attributes']['async'] ?? FALSE,
            'data_container_id' => $value['attributes']['data-container-id'] ?? FALSE,
          ];
          $chat_library['js'][] = $js;
        }
      }

      if (array_key_exists('css', $library_configuration)) {
        foreach ($library_configuration['css']['theme'] as $key => $value) {
          $css = [
            'url' => $key,
            'ext' => $value['type'] ?? FALSE,
          ];

          $chat_library['css'][] = $css;
        }
      }
    }

    // We only build it if it makes sense.
    if ($config['chat_selection']) {
      $build['leijuke'] = [
        '#title' => $this->t('Chat Leijuke'),
        '#attached' => [
          'library' => $library,
          'drupalSettings' => [
            'leijuke_data' => [
              $config['chat_selection'] => [
                'name' => $config['chat_selection'],
                'libraries' => $chat_library,
                'modulepath' => $asset_path . '/' . $module_path,
                'title' => $config['chat_title'] ? Xss::filter($config['chat_title']) : 'Chat',
              ],
            ],
          ],
        ],
      ];
    }

    return $build;
  }

}
