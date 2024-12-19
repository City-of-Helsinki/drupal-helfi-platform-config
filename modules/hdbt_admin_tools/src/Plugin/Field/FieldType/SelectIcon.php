<?php

declare(strict_types=1);

namespace Drupal\hdbt_admin_tools\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'select_icon' field type.
 *
 * Note. The field type machine name is still old select2_icon as changing
 * field types when content exists can potentially lose data.
 *
 * @FieldType(
 *   id = "select2_icon",
 *   label = @Translation("Select Icon"),
 *   category = "Helfi",
 *   default_widget = "select_icon_widget",
 *   default_formatter = "select_icon_formatter"
 * )
 * @property string $icon
 */
class SelectIcon extends FieldItemBase {

  const SELECT_ICON_CACHE = 'select_icon_cache';

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    $properties = [];
    $properties['icon'] = DataDefinition::create('string')->setLabel(t('Icon ID'));
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {
    return [
      'columns' => [
        'icon' => [
          'type' => 'char',
          'length' => 255,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    return empty($this->get('icon')->getValue());
  }

  /**
   * Load icons array.
   *
   * Load icons either from cache or load them based on the data received from
   * json-file which is saved in configuration.
   *
   * @return array
   *   Returns an array of icons or empty array.
   */
  public static function loadIcons(): array {
    if ($icons = \Drupal::cache()->get(static::SELECT_ICON_CACHE)) {
      return $icons->data;
    }
    else {
      $icons = [];
      $config = \Drupal::getContainer()->get('config.factory')->getEditable('hdbt_admin_tools.site_settings');
      $json_path = \Drupal::root() . $config->get('path_to_json');

      if (!$data = file_get_contents($json_path)) {
        \Drupal::messenger()
          ->addWarning('Failed to load icons due to missing icons data. Verify that the "path_to_json" key contains correct information in the hdbt_admin_tools.site_settings configuration. Current value: @current_value', [
            '@current_value' => $json_path,
          ]);

        return [];
      }
      $json = json_decode($data, TRUE);

      if (is_array($json) && !empty($json)) {
        $icons = array_combine($json, $json);
        \Drupal::cache()->set(static::SELECT_ICON_CACHE, $icons);
      }
    }
    return $icons;
  }

}
