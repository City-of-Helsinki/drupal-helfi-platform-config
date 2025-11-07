<?php

declare(strict_types=1);

namespace Drupal\helfi_react_search\Plugin\search_api\data_type;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiDataType;
use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Provides a comma separated string data type.
 */
#[SearchApiDataType(
  id: 'comma_separated_string',
  label: new TranslatableMarkup('Comma separated string'),
  description: new TranslatableMarkup('Comma separated string fields are used for strings that are split by a comma and stored as an array.'),
  fallback_type: 'string',
)]
class CommaSeparatedStringDataType extends DataTypePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getValue($value) {
    $array = explode(',', $value);
    $array = array_map('trim', $array);
    return $array;
  }

}
