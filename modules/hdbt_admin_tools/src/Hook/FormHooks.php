<?php

declare(strict_types=1);

namespace Drupal\hdbt_admin_tools\Hook;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Form hook implementations.
 */
class FormHooks {

  use AutowireTrait;

  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for node forms.
   *
   * @param array<string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $form_id
   *   The form id.
   */
  #[Hook('form_node_form_alter')]
  public function formNodeFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    $hero_form_ids = [
      'node_landing_page_edit_form',
      'node_landing_page_form',
      'node_page_edit_form',
      'node_page_form',
    ];

    // Allow other modules to alter the hero form ids.
    $this->moduleHandler->alter('hero_visibility', $hero_form_ids);

    // Control Hero paragraph visibility via checkbox states.
    if (in_array($form_id, $hero_form_ids)) {
      $form['field_hero']['#states'] = [
        'visible' => [
          ':input[name="field_has_hero[value]"]' => ['checked' => TRUE],
        ],
      ];
    }

    // Custom submit callback.
    $form['actions']['submit']['#submit'][] = [static::class, 'nodeFormSubmit'];
  }

  /**
   * Redirect content editor to correct translation after saving the node.
   *
   * @param array<string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function nodeFormSubmit(array $form, FormStateInterface $form_state): void {
    if (!$lang_code = $form_state->get('langcode')) {
      return;
    }
    if (!$nid = $form_state->get('nid')) {
      return;
    }
    $language = \Drupal::languageManager()->getLanguage($lang_code);
    $form_state->setRedirect('entity.node.canonical', ['node' => $nid], ['language' => $language]);
  }

}
