<?php

declare(strict_types=1);

namespace Drupal\helfi_ai\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\helfi_ai\Form\TitleSuggestionFormAlter;

/**
 * Form hook implementations for HELfi AI.
 */
class FormHooks {

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for node forms.
   *
   * Adds the AI "Suggest SEO title" button next to the title field on the
   * supported content types.
   */
  #[Hook('form_node_form_alter')]
  public function nodeFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    TitleSuggestionFormAlter::alter($form, $form_state);
  }

}
