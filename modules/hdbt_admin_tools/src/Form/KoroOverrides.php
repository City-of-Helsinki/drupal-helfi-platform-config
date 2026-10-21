<?php

declare(strict_types=1);

namespace Drupal\hdbt_admin_tools\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\hdbt_admin_tools\Config\KoroPathOverride;

/**
 * Koro override settings.
 */
final class KoroOverrides extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      KoroPathOverride::CONFIG_NAME,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'hdbt_admin_tools_koro_overrides';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);

    $form['#prefix'] = '<div class="layer-wrapper">';
    $form['#suffix'] = '</div>';

    $form['koro_overrides'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Koro overrides'),
      '#rows' => 8,
      '#default_value' => $this->formatOverrides(),
      '#description' => $this->t('One rule per line as <strong>path|wave motif</strong>, for example <strong>/savoy-talo|wave</strong>. A rule applies to the path and everything under it and the most specific rule wins. Leave the language and site prefixes out of the path and add a rule per language.'),
    ];

    $form['wave_motifs'] = [
      '#type' => 'details',
      '#title' => $this->t('Wave motifs'),
      '#open' => TRUE,
    ];

    foreach (AppearanceSettings::getWaveMotifs() as $koro => $label) {
      $form['wave_motifs'][$koro] = [
        '#type' => 'inline_template',
        '#template' => '<div class="koro-motif"><span class="koro-motif__label"><strong>{{ koro }}</strong> {{ label }}</span><div class="hds-koros hds-koros--{{ koro }}"></div></div>',
        '#context' => [
          'koro' => $koro,
          'label' => $label,
        ],
      ];
    }

    $form['#attached']['library'][] = 'hdbt_admin_tools/koro_overrides';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $motifs = AppearanceSettings::getWaveMotifs();
    $overrides = [];

    foreach (preg_split('/\R/', (string) $form_state->getValue('koro_overrides')) as $line) {
      $line = trim($line);

      if ($line === '') {
        continue;
      }
      [$path, $koro] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');

      if (!str_starts_with($path, '/')) {
        $form_state->setErrorByName('koro_overrides', $this->t('Path @path must start with a slash.', ['@path' => $path]));
        continue;
      }

      if (!array_key_exists($koro, $motifs)) {
        $form_state->setErrorByName('koro_overrides', $this->t('Wave motif @koro is not available.', ['@koro' => $koro]));
        continue;
      }
      $overrides[] = [
        'path' => rtrim($path, '/'),
        'koro' => $koro,
      ];
    }

    $form_state->setValue('koro_overrides', $overrides);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config(KoroPathOverride::CONFIG_NAME)
      ->set('koro_overrides', $form_state->getValue('koro_overrides'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Format the stored overrides for the textarea.
   *
   * @return string
   *   The overrides as one rule per line.
   */
  private function formatOverrides(): string {
    $lines = [];

    foreach ($this->config(KoroPathOverride::CONFIG_NAME)->get('koro_overrides') ?? [] as $override) {
      $lines[] = $override['path'] . '|' . $override['koro'];
    }
    return implode("\n", $lines);
  }

}
