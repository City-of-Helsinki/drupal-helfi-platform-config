<?php

namespace Drupal\helfi_hotjar\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides settings for helfi_hotjar module.
 */
class HotjarConfigForm extends ConfigFormBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'helfi_hotjar_config_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      'helfi_hotjar.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('helfi_hotjar.settings');

    $form['id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Hotjar ID'),
      '#default_value' => $config->get('hjid'),
      '#maxlength' => 64,
      '#required' => TRUE,
    ];

    $form['version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Hotjar snippet version'),
      '#default_value' => $config->get('hjsv'),
      '#maxlength' => 64,
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('helfi_hotjar.settings')
      ->set('hjid', $form_state->getValue('id'))
      ->set('hjsv', $form_state->getValue('version'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
