<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Form;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Tunes the semantic-search KNN ranking knobs.
 */
final class SearchSettingsForm extends ConfigFormBase {

  use AutowireTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'helfi_search_settings_form';
  }

  /**
   * {@inheritdoc}
   *
   * @return string[]
   *   Editable config names.
   */
  protected function getEditableConfigNames(): array {
    return ['helfi_search.settings'];
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array<string, mixed>
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);

    $bundles = $this->config('helfi_search.settings')->get('deboost_bundles') ?? [];
    $form['deboost_bundles'] = [
      '#type' => 'item',
      '#title' => $this->t('De-boosted bundles'),
      '#markup' => $bundles
        ? '<code>' . implode(', ', array_map('htmlspecialchars', $bundles)) . '</code>'
        : '<em>' . $this->t('(none)') . '</em>',
    ];

    $form['deboost_factor'] = [
      '#type' => 'number',
      '#title' => $this->t('Deboost factor'),
      '#description' => $this->t('Score multiplier applied to de-boosted bundles. 1.0 disables the effect; 0.5 halves their KNN scores.'),
      '#min' => 0,
      '#max' => 1,
      '#step' => 0.01,
      '#config_target' => 'helfi_search.settings:deboost_factor',
    ];

    $form['min_score'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum similarity'),
      '#description' => $this->t('Raw cosine-similarity floor between query and document embeddings (0.0–1.0). Hits below this threshold are dropped. Higher values return fewer but more relevant results. Calculate similarity value from desired minimum score value with similarity = min_score * 2 - 1, so 0.85 here corresponds to a document _score of about 0.7.'),
      '#min' => 0,
      '#max' => 1,
      '#step' => 0.01,
      '#config_target' => 'helfi_search.settings:min_score',
    ];

    return $form;
  }

}
