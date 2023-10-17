<?php

declare(strict_types = 1);

namespace Drupal\helfi_ckeditor\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\HTMLRestrictions;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition;
use Drupal\ckeditor5\Plugin\CKEditor5PluginElementsSubsetInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\editor\EditorInterface;
use Drupal\hdbt_admin_tools\Plugin\Field\FieldType\SelectIcon;
use Drupal\helfi_api_base\Link\InternalDomainResolver;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * CKEditor 5 HelfiLink plugin.
 *
 * @internal
 *   Plugin classes are internal.
 */
class HelfiLink extends CKEditor5PluginDefault implements CKEditor5PluginElementsSubsetInterface, ContainerFactoryPluginInterface {

  use CKEditor5PluginConfigurableTrait;

  /**
   * The internal domain resolver.
   *
   * @var \Drupal\helfi_api_base\Link\InternalDomainResolver
   */
  private InternalDomainResolver $internalDomainResolver;

  /**
   * HelfiLink constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param \Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\helfi_api_base\Link\InternalDomainResolver $internal_domain_resolver
   *   The internal domain resolver service.
   */
  public function __construct(array $configuration, string $plugin_id, CKEditor5PluginDefinition $plugin_definition, InternalDomainResolver $internal_domain_resolver) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->internalDomainResolver = $internal_domain_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('helfi_api_base.internal_domain_resolver'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['helfi_link_attributes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Tags and attributes used in Helfi link plugin'),
      '#default_value' => implode(' ', $this->configuration['helfi_link_attributes']),
      '#description' => $this->t('A list of attributes tags that can be used within the Helfi link plugin.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $form_value = $form_state->getValue('helfi_link_attributes');
    assert(is_string($form_value));
    $config_value = HTMLRestrictions::fromString($form_value)->toCKEditor5ElementsArray();
    $form_state->setValue('helfi_link_attributes', $config_value);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['helfi_link_attributes'] = $form_state->getValue('helfi_link_attributes');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'helfi_link_attributes' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getElementsSubset(): array {
    $htmlRestrictions = HTMLRestrictions::fromString(implode(' ', $this->configuration['helfi_link_attributes']));
    $plain_tags = $htmlRestrictions->extractPlainTagsSubset()->toCKEditor5ElementsArray();

    // Return the union of the "user edited" list and the
    // original configuration, but omit duplicates.
    return array_unique(array_merge(
      $plain_tags,
      $this->configuration['helfi_link_attributes']
    ));
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $config = $static_plugin_config;
    $config += [
      'link' => [
        'loadedIcons' => SelectIcon::loadIcons(),
        'whiteListedDomains' => $this->internalDomainResolver->getDomains(),
      ],
    ];
    return $config;
  }

}
