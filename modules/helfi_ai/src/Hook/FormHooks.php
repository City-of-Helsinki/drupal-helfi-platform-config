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

  public function __construct(
    private readonly TitleSuggestionFormAlter $titleSuggestionFormAlter,
  ) {
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for node forms.
   *
   * @param array<string, mixed> $form
   *   The node form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param string $form_id
   *   The id of the node form being altered.
   */
  #[Hook('form_node_form_alter')]
  public function nodeFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    $this->titleSuggestionFormAlter->alter($form, $form_state);
  }

}
