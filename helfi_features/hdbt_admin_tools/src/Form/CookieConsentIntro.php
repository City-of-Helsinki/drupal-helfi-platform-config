<?php

namespace Drupal\hdbt_admin_tools\Form;

/**
 * @file
 * Contains Drupal\hdbt_admin_tools\Form\CookieConsentIntro.
 */

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Cookie consent intro.
 */
class CookieConsentIntro extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'hdbt_admin_tools.cookie_consent_intro',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'hdbt_admin_tools_cookie_consent_intro';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('hdbt_admin_tools.cookie_consent_intro');
    $defaults = $config->get('cc');

    $form['#tree'] = TRUE;
    $form['#prefix'] = '<div class="layer-wrapper">';
    $form['#suffix'] = '</div>';

    $form['cc']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
    ];

    $form['cc']['content'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Content'),
    ];

    if (!empty($defaults)) {
      $form['cc']['title']['#default_value'] = $defaults['title'];
      $form['cc']['content']['#default_value'] = $defaults['content']['value'];
      $form['cc']['content']['#format'] = $defaults['content']['format'];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $config = $this->configFactory->getEditable('hdbt_admin_tools.cookie_consent_intro');
    $config->set('cc', $form_state->getValue('cc'))->save();
  }

}
