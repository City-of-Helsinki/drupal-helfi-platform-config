<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations\TypedData;

use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\TypedData\TypedData;

/**
 * Computed publish status.
 */
class ComputedReferencePublishedStatus extends TypedData {

  /**
   * {@inheritdoc}
   */
  public function getValue(): ?bool {
    return $this->getReferencedEntity()?->isPublished();
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($value, $notify = TRUE): void {
    $entity = $this->getReferencedEntity();
    if (!$entity || !isset($value)) {
      return;
    }

    if ($value) {
      $entity->setPublished();
    }
    else {
      $entity->setUnpublished();
    }

    // Notify the parent of any changes.
    if ($notify && isset($this->parent)) {
      $this->parent->onChange($this->name);
    }
  }

  /**
   * Gets referenced entity.
   *
   * @return \Drupal\Core\Entity\EntityPublishedInterface|null
   *   The referenced entity.
   */
  private function getReferencedEntity(): ?EntityPublishedInterface {
    $parent = $this->getParent();
    assert($parent instanceof EntityReferenceItem);

    $entity = $parent->entity;
    assert(!$entity || $entity instanceof EntityPublishedInterface);

    return $entity;
  }

}
