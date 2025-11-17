<?php

declare(strict_types=1);

namespace Drupal\helfi_react_search\Plugin\search_api\processor;

use Drupal\helfi_react_search\SupportsServiceIndexTrait;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * Checks if given TPR entity is a Hyte-service.
 *
 * @SearchApiProcessor(
 *   id = "is_hyte_service",
 *   label = @Translation("Hyte service filter"),
 *   description = @Translation("Exclude non-Hyte service entities from index"),
 *   stages = {
 *     "alter_items" = 0,
 *   }
 * )
 */
class IsHyteService extends ProcessorPluginBase {

  use SupportsServiceIndexTrait;

  /**
   * Checks the entity against these to determine if it should index.
   *
   * @var array
   */
  const HYTE_SERVICE_SYNONYMS = [
    'hh_mie',
    'hh_liik',
    'hh_rur',
    'hh_kul',
    'hh_opi',
    'hh_yht',
    'hh_vet',
    'hh_neu',
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
    $name_synonyms = $object->get('name_synonyms')->value;

    if (!$name_synonyms) {
      return FALSE;
    }

    $name_synonyms = explode(',', $name_synonyms);
    $name_synonyms = array_map('trim', $name_synonyms);

    // Check if any of the name synonyms are in the list of Hyte service terms.
    return count(array_intersect($name_synonyms, self::HYTE_SERVICE_SYNONYMS)) > 0;
  }

}
