<?php

declare(strict_types=1);

namespace Drupal\helfi_ai;

use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Rebuilds the unsaved entity from current form state for AI features.
 */
final class PreviewEntityBuilder {

  /**
   * Builds the unsaved entity from the current form state.
   *
   * @param array<string, mixed> $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The unsaved entity flagged for preview rendering, or NULL when the form
   *   is not a content entity form or no entity could be built.
   */
  public static function fromFormState(array &$form, FormStateInterface $form_state): ?ContentEntityInterface {
    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof ContentEntityFormInterface) {
      return NULL;
    }
    $entity = $form_object->buildEntity($form, $form_state);
    if (!$entity instanceof ContentEntityInterface) {
      return NULL;
    }
    // Flag the throwaway entity so the view builder renders unsaved state.
    // @phpstan-ignore-next-line
    $entity->in_preview = TRUE;
    return $entity;
  }

}
