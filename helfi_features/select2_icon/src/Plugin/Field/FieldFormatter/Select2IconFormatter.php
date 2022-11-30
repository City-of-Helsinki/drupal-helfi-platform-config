<?php

namespace Drupal\select2_icon\Plugin\Field\FieldFormatter;

use Drupal\Core\Config\Config;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'select2_icon' field formatter.
 *
 * @FieldFormatter(
 *   id = "select2_icon_formatter",
 *   label = @Translation("Select2 Icon"),
 *   field_types = {
 *     "select2_icon",
 *   }
 * )
 */
class Select2IconFormatter extends FormatterBase {

  /**
   * Contains the select2_icon.settings configuration object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected Config $select2IconConfig;

  /**
   * Constructs a FormatterBase object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Config\Config $select2_icon_config
   *   Select2 icon configuration.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, Config $select2_icon_config) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->select2IconConfig = $select2_icon_config;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('config.factory')->getEditable('select2_icon.settings')
    );
  }

  /**
   * Define how the field type is showed.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   Field items.
   * @param string $langcode
   *   Language.
   *
   * @return array
   *   Returns array of elements.
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];

    foreach ($items as $delta => $item) {
      $elements[$delta] = [
        '#theme' => 'select2_icon',
        '#icon_id' => $item->icon,
      ];
    }

    return $elements;
  }

}
