<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\DataType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\TypedData;

/**
 * Geo Shape data type.
 *
 * This converts GeoShapeItem FieldType to a format that Elasticsearch
 * understands. For more details, see:
 * https://www.elastic.co/guide/en/elasticsearch/reference/current/geo-shape.html.
 */
#[DataType(
  id: 'computed_geo_shape',
  label: new TranslatableMarkup('Geo Shape'),
)]
class GeoShape extends TypedData {

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $item = $this->getParent();

    if (!isset($item->value) || empty($item->value)) {
      return NULL;
    }

    $geojson = json_decode($item->value, TRUE);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($geojson)) {
      return NULL;
    }

    // Elasticsearch expects geo_shape in GeoJSON format.
    // Validate required properties.
    if (!isset($geojson['type']) || !isset($geojson['coordinates'])) {
      return NULL;
    }

    // Return the geometry in Elasticsearch geo_shape format.
    return (object) [
      'type' => strtolower($geojson['type']),
      'coordinates' => $geojson['coordinates'],
    ];
  }

}
