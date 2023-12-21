<?php

declare(strict_types = 1);

namespace Drupal\helfi_react_search\Plugin\search_api\processor;

use Drupal\helfi_react_search\SupportsUnitIndexTrait;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * Checks if given TPR entity is a maternity and child health clinic.
 *
 * @SearchApiProcessor(
 *   id = "maternity_and_child_health_clinic",
 *   label = @Translation("Maternity and child health clinic filter"),
 *   description = @Translation("Exclude other entities from index"),
 *   stages = {
 *     "alter_items" = 0,
 *   }
 * )
 */
class IsMaternityAndChildHealthClinic extends ProcessorPluginBase {

  use SupportsUnitIndexTrait;

  /**
   * Checks the entity against these to determine if it should index.
   *
   * @var array
   */
  const CHILD_HEALTH_CLINIC_SERVICE_IDS = [
    '3210',
    '6440',
    '6441',
  ];

  /**
   * {@inheritdoc}
   */
  public function alterIndexedItems(array &$items): void {
    foreach ($items as $id => $item) {
      $shouldIndex = $this->shouldIndex($item);

      if (!$shouldIndex) {
        unset($items[$id]);
      }
    }
  }

  /**
   * Determine if entity should be indexed.
   *
   * @param \Drupal\search_api\Item\ItemInterface $item
   *   Item to check.
   *
   * @return bool
   *   The result.
   */
  protected function shouldIndex(ItemInterface $item): bool {
    $object = $item->getOriginalObject()->getValue();
    $fieldValues = $object->get('services')->getValue();

    $flatValues = array_map(function (array $fieldValue) {
      return $fieldValue['target_id'];
    }, $fieldValues);

    if (count(array_intersect($flatValues, self::CHILD_HEALTH_CLINIC_SERVICE_IDS))) {
      return TRUE;
    }

    return FALSE;
  }

}
