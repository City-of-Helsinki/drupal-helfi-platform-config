<?php

declare(strict_types=1);

namespace Drupal\helfi_ai;

use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Rebuilds the unsaved entity from current form state for AI features.
 *
 * AI features that summarise or rephrase "what the editor is looking at right
 * now" need the live, unsaved form values — body edits, unsaved paragraphs and
 * all — not the saved node. Both the AI summary widget and the SEO title
 * suggester build that throwaway entity the same way; this collects it in one
 * place.
 */
final class PreviewEntityBuilder {

  /**
   * Builds the unsaved entity from the current form state.
   *
   * Must be called from an AJAX callback of a plain (non-submit) button that
   * carries no #limit_validation_errors, so the submitted values are still
   * un-pruned and {@see ContentEntityFormInterface::buildEntity()} can
   * reconstruct the in-memory entity including unsaved changes.
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
    // Throwaway clone holding the editor's unsaved changes. Marking it as a
    // preview makes the view builder render the in-memory state and skip the
    // render cache — otherwise an existing node would render its saved (cached)
    // content. in_preview is an untyped dynamic property core's NodeViewBuilder
    // reads.
    // @phpstan-ignore-next-line
    $entity->in_preview = TRUE;
    return $entity;
  }

}
