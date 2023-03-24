<?php

declare(strict_types = 1);

namespace Drupal\helfi_eu_cookie_compliance\Plugin\Block;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\helfi_eu_cookie_compliance\Form\EuCookieComplianceBlockForm;

/**
 * EU Cookie Compliance Block.
 *
 * This block is shown on '/cookie-information-and-settings' page
 * and allows users to update cookie consent settings.
 *
 * @Block(
 *  id = "eu_cookie_compliance_block",
 *  admin_label = @Translation("EU Cookie Compliance Block"),
 * )
 */
class EuCookieComplianceBlock extends BlockBase {

  /**
   * Return block settings.
   */
  private function getBlockSettings() {
    $config = $this->getConfiguration();
    return !empty($config[$this->getBaseId() . '_settings']) ? $config[$this->getBaseId() . '_settings'] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function build() : array {
    $form = \Drupal::formBuilder()->getForm(EuCookieComplianceBlockForm::class);

    if (!isset($form['categories'])) {
      return [];
    }

    $build = [];

    $settings = $this->getBlockSettings();

    $value = !empty($settings['description']['value']) ? $settings['description']['value'] : NULL;
    $format = !empty($settings['description']['format']) ? $settings['description']['format'] : NULL;

    if ($value && $format) {

      $build['description'] = [
        '#type' => 'processed_text',
        '#format' => $format,
        '#text' => $value,
      ];
    }
    $build['form'] = $form;

    return $build;

  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) : array {
    $settings = $this->getBlockSettings();

    $form[$this->getBaseId() . '_settings']['description'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Description'),
      '#default_value' => !empty($settings['description']['value']) ? $settings['description']['value'] : NULL,
      '#format' => !empty($settings['description']['format']) ? $settings['description']['format'] : NULL,
      '#description' => $this->t('Provide some information about the form shown and EU Cookie Compliance Categories.'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) : void {
    $settingsKey = $this->getBaseId() . '_settings';
    $this->configuration[$settingsKey] = $form_state->getValue($settingsKey);
  }

}
