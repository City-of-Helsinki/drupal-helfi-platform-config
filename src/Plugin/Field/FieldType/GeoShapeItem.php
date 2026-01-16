<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines the 'geo_shape' field type.
 *
 * Stores GeoJSON geometry for Elasticsearch geo_shape queries.
 */
#[FieldType(
  id: "geo_shape",
  label: new TranslatableMarkup("Geo Shape"),
  description: new TranslatableMarkup("GeoJSON geometry for Elasticsearch geo_shape queries."),
)]
final class GeoShapeItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    $value = $this->get('value')->getValue();
    return empty($value);
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    $properties['value'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('GeoJSON'))
      ->setDescription(new TranslatableMarkup('GeoJSON geometry string.'));

    $properties['geo_shape'] = DataDefinition::create('computed_geo_shape')
      ->setLabel(new TranslatableMarkup('Geo Shape'))
      ->setDescription(new TranslatableMarkup('Computed geo_shape for Elasticsearch.'))
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
        'value' => [
          'type' => 'text',
          'size' => 'normal',
        ],
      ],
    ];
  }

}
