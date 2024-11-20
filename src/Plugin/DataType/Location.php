<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\DataType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\TypedData;

/**
 * Location data type.
 *
 * This converts LocationItem FieldType to a format that elasticsearch
 * understands. For more details, see:
 * https://www.elastic.co/guide/en/elasticsearch/reference/current/geo-point.html.
 */
#[DataType(
  id: 'computed_location',
  label: new TranslatableMarkup('Location'),
)]
class Location extends TypedData {

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $item = $this->getParent();

    if (isset($item->latitude, $item->longitude)) {
      return (object) [
        'lat' => (float) $item->latitude,
        'lon' => (float) $item->longitude,
      ];
    }

    return NULL;
  }

}
