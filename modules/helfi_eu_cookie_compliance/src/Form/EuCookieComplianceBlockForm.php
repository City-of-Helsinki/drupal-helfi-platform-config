<?php

declare(strict_types = 1);

namespace Drupal\helfi_eu_cookie_compliance\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generate the form displayed inside the EuCookieComplianceBlock.
 */
class EuCookieComplianceBlockForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() : string {
    return 'eu_cookie_compliance_block_form';
  }

  /**
   * Eu cookie compliance settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected static $config;

  /**
   * Eu cookie compliance cookie.
   *
   * @var string
   */
  protected static $cookieName;

  /**
   * Eu cookie compliance policy version.
   *
   * @var string
   */
  protected static string $cookiePolicyVersion;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    self::$config = \Drupal::config('eu_cookie_compliance.settings');
    self::$cookieName = !empty(self::$config->get('cookie_name')) ? self::$config->get('cookie_name') : 'cookie-agreed';
    self::$cookiePolicyVersion = !empty(self::$config->get('cookie_policy_version')) ? self::$config->get('cookie_policy_version') : 'unknown';
    return new static();
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = self::$config;
    $current_cookie_value = isset($_COOKIE[self::$cookieName]) ? $_COOKIE[self::$cookieName] : null;

    if ($config->get('method') !== 'categories') {
      return;
    }

    $eu_cookie_categories = \Drupal::entityTypeManager()->getStorage('cookie_category')->getCookieCategories();

    $cookie_categories = [];
    $cookie_categories_descriptions = [];
    foreach ($eu_cookie_categories as $key => $value) {
      $cookie_categories[$key] = $value['label'];
      $cookie_categories_descriptions[$key] = ['#description' => $value['description']['value']];
    }

    // If user has already chosen something, show helpful information
    if ($current_cookie_value !== null) {
      if ($current_cookie_value == 0) {
        $form['#markup'] = '<div class="cookie-selection-instruction">' . $this->t('<p>Your current setting is to <strong>only allow essential cookies, that are required for the site to function correctly.</strong> Submit the form below to make changes.</p>') . '</div>';
      } else {
        $form['#markup'] = '<div class="cookie-selection-instruction">' . $this->t('<p>Your current cookie settings are below. Submit the form to make changes.</p>') . '</div>';
      }
    } else {
      $form['#markup'] = '<div class="cookie-selection-instruction">' . $this->t('<p><strong>You have not saved any cookie preferences.</strong> By default only essential cookies are saved. See details below.</p>') . '</div>';
    }

    $form['categories'] = [
      '#type' => 'checkboxes',
      '#options' => $cookie_categories,
      '#attributes' => [
        'class' => ['categories'],
      ],
    ];
    $form['categories'] += $cookie_categories_descriptions;

    foreach ($eu_cookie_categories as $key => $value) {
      if (isset($value['checkbox_default_state']) && $value['checkbox_default_state'] === 'required') {
        $form['categories'][$key] += [
          '#default_value' => $key,
          '#value' => $key,
          '#attributes' => [
            'checked' => TRUE,
            'disabled' => TRUE,
          ],
        ];
      }
    }

    $form['buttons'] = [
      'save' => [
        '#type' => 'submit',
        '#value' => $config->get('save_preferences_button_label'),
        '#attributes' => [
          'class' => ['save'],
        ],
      ],
      'accept_all' => [
        '#type' => 'submit',
        '#value' => $config->get('accept_all_categories_button_label'),
        '#submit' => ['::submitAcceptAllHandler'],
        '#attributes' => [
          'class' => ['accept'],
        ],
        '#prefix' => '<span class="hidden">',
        '#suffix' => '</span>',
      ],
      'withdraw' => [
        '#type' => 'submit',
        '#value' => $config->get('withdraw_action_button_label'),
        '#submit' => ['::submitWithdrawHandler'],
        '#attributes' => [
          'class' => ['withdraw'],
        ],
        '#prefix' => '<span class="hidden">',
        '#suffix' => '</span>',
      ],
      '#type' => 'container',
      '#wrapper' => 'div',
      '#attributes' => [
        'class' => ['buttons'],
      ],
    ];

    $form['#attached'] = [
      'library' => [
        'eu_cookie_compliance/eu_cookie_compliance_cookie_values',
      ],
      'drupalSettings' => [
        'eu_cookie_compliance_cookie_values' => [
          'cookieName' => self::$cookieName,
          'cookieCategories' => array_keys($cookie_categories),
        ],
      ],
    ];

    $form['#cache']['contexts'][] = 'session';

    return $form;
  }

  /**
   * Default submission handler for saving selected categories.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $cookie_lifetime = self::$config->get('cookie_lifetime');
    $values = array_reverse($form_state->getValue('categories'));

    $selected = [];
    foreach ($values as $key => $value) {
      if ($value) {
        $selected[] = $key;
      }
    }
    $values = $this->stringify($selected);

    $time = \Drupal::time()->getRequestTime() + ($cookie_lifetime * 24 * 60 * 60);
    setrawcookie(self::$cookieName, '2', $time, '/');
    setrawcookie(self::$cookieName . '-categories', $values, $time, '/');
    setrawcookie(self::$cookieName . '-version', self::$cookiePolicyVersion, $time, '/');
  }

  /**
   * Custom submission handler for accepting all categories.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitAcceptAllHandler(array &$form, FormStateInterface $form_state) {
    $cookie_lifetime = self::$config->get('cookie_lifetime');
    $values = $this->stringify(array_keys($form_state->getValue('categories')));
    $time = \Drupal::time()->getRequestTime() + ($cookie_lifetime * 24 * 60 * 60);
    setrawcookie(self::$cookieName, '2', $time, '/');
    setrawcookie(self::$cookieName . '-categories', $values, $time, '/');
    setrawcookie(self::$cookieName . '-version', self::$cookiePolicyVersion, $time, '/');
  }

  /**
   * Custom submission handler for withdrawing consent for all categories.
   */
  public function submitWithdrawHandler(array &$form, FormStateInterface $form_state) {
    $first_read_only = !empty(self::$config->get('fix_first_cookie_category')) ? self::$config->get('fix_first_cookie_category') : FALSE;
    if (!$first_read_only) {
      setrawcookie(self::$cookieName, '', \Drupal::time()->getRequestTime() - 3600, '/');
    }
    setrawcookie(self::$cookieName . '-categories', '', \Drupal::time()->getRequestTime() - 3600, '/');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

  /**
   * Replace reserved characters in json.
   *
   * @var array
   *
   * @return string
   *   'Sanitized' string
   */
  private function stringify($values) {
    $json = JSON::encode($values);
    $json = str_replace('[', '%5B', $json);
    $json = str_replace(']', '%5D', $json);
    $json = str_replace('"', '%22', $json);
    $json = str_replace(',', '%2C', $json);
    return $json;
  }

}
