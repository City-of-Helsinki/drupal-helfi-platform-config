<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Form;

/**
 * @file
 * Contains Drupal\helfi_platform_config\Form\MatomoSettings.
 */

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Matomo settings.
 */
class MatomoSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      'helfi_platform_config.matomo_settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('helfi_platform_config.matomo_settings');

    $form['matomo_site_id'] = [
      '#default_value' => $config->get('matomo_site_id'),
      '#description' => $this->t('Matomo site ID that matches the project in Matomo admin. Used to link the analytics of the site to Matomo'),
      '#title' => $this->t('Matomo site ID'),
      '#type' => 'textfield',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'helfi_matomo_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('helfi_platform_config.matomo_settings');
    $config->set('matomo_site_id', $form_state->getValue('matomo_site_id'));
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
