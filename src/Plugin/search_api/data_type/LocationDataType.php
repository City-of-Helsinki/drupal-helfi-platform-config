<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\search_api\data_type;

use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Provides a string data type.
 *
 * @SearchApiDataType(
 *   id = "location",
 *   label = @Translation("Location"),
 *   description = @Translation("Elasticsearch geo_point type."),
 *   default = "true"
 * )
 */
class LocationDataType extends DataTypePluginBase {
}
