<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\helfi_platform_config\TypedData\LocationDataDefinition;

/**
 * Defines the 'location' field type.
 */
#[FieldType(
  id: "location",
  label: new TranslatableMarkup("Location"),
  description: new TranslatableMarkup("WGS84 coordinates."),
  default_widget: "location",
)]
final class LocationItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    return empty($this->get('latitude')->getValue()) || empty($this->get('longitude')->getValue());
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    $properties['latitude'] = DataDefinition::create('float')
      ->setLabel(new TranslatableMarkup('Latitude'));

    $properties['longitude'] = DataDefinition::create('float')
      ->setLabel(new TranslatableMarkup('Longitude'));

    $properties['value'] = DataDefinition::create('computed_location')
      ->setLabel(new TranslatableMarkup('Coordinates'))
      ->setComputed(TRUE)
      ->setReadOnly(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {
    return [
      'columns' => [
        'latitude' => [
          'type' => 'float',
          'size' => 'normal',
        ],
        'longitude' => [
          'type' => 'float',
          'size' => 'normal',
        ],
      ],
    ];
  }

}
