<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\search_api\data_type;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiDataType;
use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Provides a geo_shape data type for Search API.
 */
#[SearchApiDataType(
  id: 'geo_shape',
  label: new TranslatableMarkup('Geo Shape'),
  description: new TranslatableMarkup('Elasticsearch geo_shape type for geometry queries.'),
)]
final class GeoShapeDataType extends DataTypePluginBase {
}
