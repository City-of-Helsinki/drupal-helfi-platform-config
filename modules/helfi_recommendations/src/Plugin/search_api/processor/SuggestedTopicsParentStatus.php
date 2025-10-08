<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations\Plugin\search_api\processor;

use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\helfi_recommendations\Entity\SuggestedTopicsInterface;

/**
 * Excludes suggested topics with unpublished parent entities.
 */
#[SearchApiProcessor(
  id: 'suggested_topics_parent_status',
  label: new TranslatableMarkup('Suggested topics parent status'),
  description: new TranslatableMarkup('Exclude suggested topics with unpublished parent entities from being indexed.'),
  stages: [
    'alter_items' => 1,
  ],
)]
class SuggestedTopicsParentStatus extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index) {
    foreach ($index->getDatasources() as $datasource) {
      $entity_type_id = $datasource->getEntityTypeId();
      if (!$entity_type_id) {
        continue;
      }
      // We support suggested topics.
      if ($entity_type_id === 'suggested_topics') {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function alterIndexedItems(array &$items) {
    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($items as $item_id => $item) {
      $object = $item->getOriginalObject()->getValue();
      $enabled = TRUE;
      if ($object instanceof SuggestedTopicsInterface) {
        $parent = $object->getParentEntity();
        if (!$parent) {
          // Do not index suggested topics with no parent entity.
          $enabled = FALSE;
        }
        if ($parent instanceof EntityPublishedInterface && !$parent->isPublished()) {
          // Do not index suggested topics with an unpublished parent entity.
          $enabled = FALSE;
        }
      }
      if (!$enabled) {
        unset($items[$item_id]);
      }
    }
  }

}
