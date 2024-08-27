<?php

declare(strict_types=1);

namespace Drupal\hdbt_cookie_banner\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * HDBT cookie banner form.
 */
final class HdbtCookieBannerForm extends ConfigFormBase {

  /**
   * Config settings.
   */
  public const SETTINGS = 'hdbt_cookie_banner.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'hdbt_cookie_banner';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
     self::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['site_settings'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Site settings', options: ['context' => 'hdbt cookie banner']),
      '#config_target' => self::SETTINGS . ":site_settings",
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $values = $form_state->getValues();

    if (!$this->isValidJson($values['site_settings'])) {
      $form_state->setErrorByName('site_settings',
        $this->t('Site settings must be valid JSON', options: ['context' => 'hdbt cookie banner'])
      );
    }
  }

  /**
   * Validates JSON string.
   *
   * @param string $value
   *   Input string.
   *
   * @return bool
   *   True if input is valid JSON.
   */
  private function isValidJson(string $value): bool {
    // @todo replace with json_validate in php >= 8.3.
    // https://www.php.net/releases/8.3/en.php#json_validate.
    json_decode($value);
    return json_last_error() === JSON_ERROR_NONE;
  }

}
