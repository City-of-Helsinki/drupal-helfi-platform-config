<?php

declare(strict_types = 1);

namespace Drupal\helfi_react_search;

use Drupal\search_api\IndexInterface;

/**
 * Trait for enabling a processor for TPR unit indices only.
 */
trait SupportsUnitIndexTrait {

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index): bool {
    foreach ($index->getDatasources() as $datasource) {
      $entityTypeId = $datasource->getEntityTypeId();
      if (!$entityTypeId) {
        continue;
      }

      if ($entityTypeId === 'tpr_unit') {
        return TRUE;
      }
    }

    return FALSE;
  }

}
