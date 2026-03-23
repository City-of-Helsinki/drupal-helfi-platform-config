<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\search_api\data_type;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiDataType;
use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Provides a string data type.
 */
#[SearchApiDataType(
  id: 'location',
  label: new TranslatableMarkup('Location'),
  description: new TranslatableMarkup('Elasticsearch geo_point type.'),
  default: TRUE,
)]
class LocationDataType extends DataTypePluginBase {
}
