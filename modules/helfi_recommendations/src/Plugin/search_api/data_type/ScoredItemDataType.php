<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations\Plugin\search_api\data_type;

use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Provides a string data type.
 *
 * @SearchApiDataType(
 *   id = "scored_item",
 *   label = @Translation("Scored item"),
 *   description = @Translation("Item with score."),
 *   fallback_type = "object"
 * )
 */
class ScoredItemDataType extends DataTypePluginBase {
}
