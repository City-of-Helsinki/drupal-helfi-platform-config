<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_curated_event_list\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_paragraphs_curated_event_list\Entity\LinkedEventsEvent;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Provides Paragraph hooks.
 */
final readonly class ParagraphHooks {

  public function __construct(private MessengerInterface $messenger) {
  }

  /**
   * Validates the given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to validate.
   *
   * @return bool
   *   TRUE if the entity is valid.
   */
  private function isValidEntity(EntityInterface $entity) : bool {
    if (!$entity instanceof ParagraphInterface) {
      return FALSE;
    }
    return $entity->bundle() === 'curated_event_list';
  }

  /**
   * Implements hook_ENTITY_TYPE_presave().
   *
   * Removes all ended events from Curated event list when
   * the parent paragraph is saved.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   */
  #[Hook(hook: 'paragraph_presave')]
  public function preSave(EntityInterface $entity): void {
    if (!$this->isValidEntity($entity)) {
      return;
    }

    $field = $entity->get('field_events');
    assert($field instanceof EntityReferenceFieldItemListInterface);

    foreach ($field->referencedEntities() as $delta => $event) {
      assert($event instanceof LinkedEventsEvent);

      if (!$event->hasEnded()) {
        continue;
      }
      // Remove ended events.
      $field->removeItem($delta);

      $this->messenger
        ->addStatus(new TranslatableMarkup('Removed "@label" because the event has ended.', [
          '@label' => $event->label(),
        ]));
    }

  }

}
