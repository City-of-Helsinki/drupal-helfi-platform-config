<?php

declare(strict_types=1);

namespace Drupal\hdbt_cookie_banner\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * HDBT cookie banner form.
 */
final class HdbtCookieBannerForm extends ConfigFormBase {

  /**
   * Config settings.
   */
  public const SETTINGS = 'hdbt_cookie_banner.settings';

  /**
   * The app root.
   *
   * @var string
   */
  protected string $appRoot;

  /**
   * Extension path resolver.
   *
   * @var \Drupal\Core\Extension\ExtensionPathResolver
   */
  protected ExtensionPathResolver $extensionPathResolver;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    ExtensionPathResolver $extension_path_resolver,
    string $app_root,
  ) {
    parent::__construct($config_factory);
    $this->extensionPathResolver = $extension_path_resolver;
    $this->appRoot = $app_root;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('extension.path.resolver'),
      $container->getParameter('app.root'),
    );
  }

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

    $form['settings'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Settings'),
    ];

    $form['json_editor_container'] = [
      '#title' => $this->t('Cookie consent settings'),
      '#type' => 'details',
      '#group' => 'settings',
    ];

    $form['json_editor_container']['use_custom_settings'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use custom cookie settings'),
      '#description' => $this->t('By default, cookie settings and HDS cookie consent JavaScript file are loaded from Hel.fi Etusivu instance. By selecting this override option, you can use your own settings and override HDS cookie consent JS file.'),
      '#config_target' => self::SETTINGS . ':use_custom_settings',
    ];

    $form['json_editor_container']['use_external_hds_cookie_js'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use external HDS cookie consent JavaScript file'),
      '#description' => $this->t('When the <em>Use custom cookie settings</em> option is selected, the local HDS cookie consent JavaScript file is loaded instead of Etusivu HDS cookie consent. Select this option to use another HDS cookie consent JavaScript file.'),
      '#config_target' => self::SETTINGS . ':use_external_hds_cookie_js',
      '#states' => [
        'invisible' => [
          ':input[name="use_custom_settings"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['json_editor_container']['hds_cookie_js_override'] = [
      '#type' => 'textfield',
      '#title' => $this->t('HDS cookie consent JavaScript URL', options: ['context' => 'hdbt cookie banner']),
      '#config_target' => self::SETTINGS . ':hds_cookie_js_override',
      '#description' => $this->t('The URL of the JavaScript file that should be used instead of Etusivu HDS cookie consent. ', options: ['context' => 'hdbt cookie banner']),
      '#states' => [
        'invisible' => [
          ':input[name="use_custom_settings"]' => ['checked' => FALSE],
        ],
        'disabled' => [
          ':input[name="use_external_hds_cookie_js"]' => ['checked' => FALSE],
        ],
        'required' => [
          ':input[name="use_external_hds_cookie_js"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['json_editor_container']['json_editor'] = [
      '#type' => 'markup',
      '#markup' => '<div class="json_editor"><h1>HDS Cookie Consent Settings</h1><div id="language_holder"></div><div id="editor_holder"></div></div>',
      '#attached' => [
        'library' => [
          'hdbt_cookie_banner/cookie_banner_admin_ui',
        ],
        'drupalSettings' => [
          'cookieBannerAdminUi' => [
            'siteSettingsSchema' => $this->getSchemaJson(),
          ],
        ],
      ],
      '#states' => [
        'invisible' => [
          ':input[name="use_custom_settings"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['json_editor_container']['site_settings'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Site settings', options: ['context' => 'hdbt cookie banner']),
      '#config_target' => self::SETTINGS . ':site_settings',
      '#states' => [
        'invisible' => [
          ':input[name="use_custom_settings"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['general_settings'] = [
      '#title' => $this->t('General settings', options: ['context' => 'hdbt cookie banner']),
      '#type' => 'details',
      '#group' => 'settings',
    ];

    $form['general_settings']['settings_page_selector'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Settings page selector', options: ['context' => 'hdbt cookie banner']),
      '#config_target' => self::SETTINGS . ':settings_page_selector',
      '#description' => $this->t('Insert a CSS selector to which the cookie settings should be appended. Defaults to <em>.cookie-policy-settings</em>', options: ['context' => 'hdbt cookie banner']),
    ];

//    $form['general_settings']['hds_cookie_js_override'] = [
//      '#type' => 'textfield',
//      '#title' => $this->t('Override HDS cookie consent javascript', options: ['context' => 'hdbt cookie banner']),
//      '#config_target' => self::SETTINGS . ':settings_page_selector.title',
//      '#description' => $this->t('Usage: To use a different HDS cookie consent JSInsert an URL to the javascript file.', options: ['context' => 'hdbt cookie banner']),
//    ];

    $form['general_settings']['theme'] = [
      '#type' => 'select',
      '#title' => $this->t('Cookie banner theme', options: ['context' => 'hdbt cookie banner']),
      '#options' => [
        'black' => $this->t('Black', options: ['context' => 'hdbt cookie banner']),
        'white' => $this->t('White', options: ['context' => 'hdbt cookie banner']),
      ],
      '#required' => TRUE,
      '#config_target' => self::SETTINGS . ':theme',
    ];

    $form['general_settings']['cookie_information_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cookie policy page title', options: ['context' => 'hdbt cookie banner']),
      '#config_target' => self::SETTINGS . ':cookie_information.title',
    ];

    $form['general_settings']['cookie_information_content'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Cookie policy page content', options: ['context' => 'hdbt cookie banner']),
      '#config_target' => self::SETTINGS . ':cookie_information.content',
      '#rows' => 5,
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

  /**
   * Get schema json for the site settings.
   *
   * @return string
   *   Schema json.
   */
  private function getSchemaJson(): string {
    $site_settings_schema = $this->appRoot . '/' . $this->extensionPathResolver->getPath('module', 'hdbt_cookie_banner') . '/assets/json/siteSettings.schema.json';
    if ($json = file_get_contents($site_settings_schema)) {
      return json_encode(json_decode($json));
    }
    return '';
  }

}
