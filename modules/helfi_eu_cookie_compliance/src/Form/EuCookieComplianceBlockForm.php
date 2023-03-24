<?php

declare(strict_types = 1);

namespace Drupal\helfi_eu_cookie_compliance\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eu_cookie_compliance\CategoryStorageManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generate the form displayed inside the EuCookieComplianceBlock.
 */
final class EuCookieComplianceBlockForm extends FormBase {

  /**
   * Eu cookie compliance settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private ImmutableConfig $config;

  /**
   * Eu cookie compliance cookie.
   *
   * @var string
   */
  private string $cookieName;

  /**
   * Eu cookie compliance policy version.
   *
   * @var string
   */
  private string $cookiePolicyVersion;

  /**
   * The cookie category storage manager.
   *
   * @var \Drupal\eu_cookie_compliance\CategoryStorageManager
   */
  private CategoryStorageManager $categoryStorageManager;

  /**
   * The time component.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  private TimeInterface $time;

  /**
   * {@inheritdoc}
   */
  public function getFormId() : string {
    return 'eu_cookie_compliance_block_form';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) : self {
    $instance = parent::create($container);
    $configFactory = $container->get('config.factory');

    $instance->config = $configFactory->get('eu_cookie_compliance.settings');
    $instance->cookieName = $instance->config->get('cookie_name') ?: 'cookie-agreed';
    $instance->cookiePolicyVersion = $instance->config->get('cookie_policy_version') ?: 'unknown';
    $instance->categoryStorageManager = $container->get('entity_type.manager')->getStorage('cookie_category');
    $instance->time = $container->get('datetime.time');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $current_cookie_value = $_COOKIE[$this->cookieName] ?? NULL;

    if ($this->config->get('method') !== 'categories') {
      return [];
    }

    $eu_cookie_categories = $this->categoryStorageManager->getCookieCategories();

    $cookie_categories = [];
    $cookie_categories_descriptions = [];
    foreach ($eu_cookie_categories as $key => $value) {
      $cookie_categories[$key] = $value['label'];
      $cookie_categories_descriptions[$key] = ['#description' => $value['description']['value']];
    }

    $form['#markup'] = match($current_cookie_value) {
      NULL => '<div class="cookie-selection-instruction">' . $this->t('<p><strong>You have not saved any cookie preferences.</strong> By default only essential cookies are saved. See details below.</p>') . '</div>',
      '0' => '<div class="cookie-selection-instruction">' . $this->t('<p>Your current setting is to <strong>only allow essential cookies, that are required for the site to function correctly.</strong> Submit the form below to make changes.</p>') . '</div>',
      default => '<div class="cookie-selection-instruction">' . $this->t('<p>Your current cookie settings are below. Submit the form to make changes.</p>') . '</div>',
    };

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
        '#value' => $this->config->get('save_preferences_button_label'),
        '#attributes' => [
          'class' => ['save'],
        ],
      ],
      'accept_all' => [
        '#type' => 'submit',
        '#value' => $this->config->get('accept_all_categories_button_label'),
        '#submit' => ['::submitAcceptAllHandler'],
        '#attributes' => [
          'class' => ['accept'],
        ],
        '#prefix' => '<span class="hidden">',
        '#suffix' => '</span>',
      ],
      'withdraw' => [
        '#type' => 'submit',
        '#value' => $this->config->get('withdraw_action_button_label'),
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
        'helfi_eu_cookie_compliance/eu_cookie_compliance_cookie_values',
      ],
      'drupalSettings' => [
        'eu_cookie_compliance_cookie_values' => [
          'cookieName' => $this->cookieName,
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
  public function submitForm(array &$form, FormStateInterface $form_state) : void {
    $values = array_reverse($form_state->getValue('categories'));

    $selected = [];
    foreach ($values as $key => $value) {
      if ($value) {
        $selected[] = $key;
      }
    }
    $this->submitCookieValues($selected);
  }

  /**
   * Submits the cookie categories.
   *
   * @param array $categories
   *   The categories.
   */
  private function submitCookieValues(array $categories) : void {
    $cookie_lifetime = (int) $this->config->get('cookie_lifetime');
    $time = $this->time->getRequestTime() + ($cookie_lifetime * 24 * 60 * 60);

    setrawcookie($this->cookieName, '2', $time, '/');
    setrawcookie($this->cookieName . '-categories', $this->stringify($categories), $time, '/');
    setrawcookie($this->cookieName . '-version', $this->cookiePolicyVersion, $time, '/');
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
    $values = array_keys($form_state->getValue('categories'));
    $this->submitCookieValues($values);
  }

  /**
   * Custom submission handler for withdrawing consent for all categories.
   */
  public function submitWithdrawHandler(array &$form, FormStateInterface $form_state) {
    $time = $this->time->getRequestTime() - 3600;

    if (!$this->config->get('fix_first_cookie_category')) {
      setrawcookie($this->cookieName, '', $time, '/');
    }
    setrawcookie($this->cookieName . '-categories', '', $time, '/');
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
