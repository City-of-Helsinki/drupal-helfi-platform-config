<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Plugin\search_api\data_type;

use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Provides an embeddings data type.
 *
 * @SearchApiDataType(
 *   id = "embeddings",
 *   label = @Translation("Embeddings"),
 *   description = @Translation("Embeddings vector."),
 *   fallback_type = "object"
 * )
 */
class Embeddings extends DataTypePluginBase {
}
