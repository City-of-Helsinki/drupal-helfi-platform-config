<?php

declare(strict_types=1);

namespace Drupal\helfi_toc\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Form hooks for HELfi Table of contents.
 */
class FormHooks {

  /**
   * Implements hook_form_FORM_ID_alter() for tpr_service_form.
   *
   * @param array<string, mixed> $form
   *   The form.
   */
  #[Hook('form_tpr_service_form_alter')]
  public function tprServiceFormAlter(array &$form): void {
    $this->applyFormTheme($form);
  }

  /**
   * Implements hook_form_FORM_ID_alter() for tpr_unit_form.
   *
   * @param array<string, mixed> $form
   *   The form.
   */
  #[Hook('form_tpr_unit_form_alter')]
  public function tprUnitFormAlter(array &$form): void {
    $this->applyFormTheme($form);
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for node forms.
   *
   * @param array<string, mixed> $form
   *   The form.
   */
  #[Hook('form_node_form_alter')]
  public function nodeFormAlter(array &$form): void {
    $this->applyFormTheme($form);
  }

  /**
   * Handle Table of contents fields visibility and access.
   *
   * @param array<string, mixed> $form
   *   The form.
   */
  private function applyFormTheme(array &$form): void {
    $whitelisted_forms = [
      'node_page_edit_form',
      'node_page_form',
      'tpr_service_form',
      'tpr_unit_form',
      'node_district_edit_form',
      'node_district_form',
    ];

    $form['toc_enabled']['#access'] = FALSE;
    $form['toc_title']['#access'] = FALSE;

    // Control Table of contents title field visibility via checkbox states.
    if (in_array($form['#form_id'], $whitelisted_forms)) {
      $form['toc_enabled']['#access'] = TRUE;
      $form['toc_title']['#access'] = FALSE;
      $form['toc_title']['#states'] = [
        'visible' => [
          ':input[name="toc_enabled[value]"]' => ['checked' => FALSE],
        ],
      ];
    }
  }

}
