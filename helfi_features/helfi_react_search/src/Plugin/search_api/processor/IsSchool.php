<?php

declare(strict_types = 1);

namespace Drupal\helfi_react_search\Plugin\search_api\processor;

use Drupal\helfi_react_search\SupportsUnitIndexTrait;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Item\ItemInterface;

/**
 * Checks if given TPR entity is a school.
 *
 * @SearchApiProcessor(
 *   id = "is_school",
 *   label = @Translation("School filter"),
 *   description = @Translation("Exclude non-school entities from index"),
 *   stages = {
 *     "alter_items" = 0,
 *   }
 * )
 */
class IsSchool extends ProcessorPluginBase {

  use SupportsUnitIndexTrait;

  /**
   * Checks the entity against these to determine if it should index.
   *
   * @var array
   */
  const SCHOOL_SERVICE_IDS = [
    '3105',
    '3106',
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

    if (count(array_intersect($flatValues, self::SCHOOL_SERVICE_IDS))) {
      return TRUE;
    }

    return FALSE;
  }

}
