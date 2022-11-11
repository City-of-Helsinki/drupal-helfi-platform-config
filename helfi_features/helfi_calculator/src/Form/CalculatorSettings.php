<?php

namespace Drupal\helfi_calculator\Form;

/**
 * @file
 * Contains Drupal\helfi_calculator\Form\CalculatorSettings.
 */

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\language\Config\LanguageConfigOverride;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Calculator settings.
 */
class CalculatorSettings extends ConfigFormBase {

  const CALCULATOR_SETTINGS_CONFIGURATION = 'helfi_calculator.calculator_settings';

  /**
   * The configurable language manager.
   *
   * @var \Drupal\language\ConfigurableLanguageManagerInterface
   */
  protected ConfigurableLanguageManagerInterface $languageManager;

  /**
   * The configuration name.
   *
   * @var string
   */
  protected string $configName = self::CALCULATOR_SETTINGS_CONFIGURATION;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, ConfigurableLanguageManagerInterface $language_manager) {
    parent::__construct($config_factory);
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'helfi_calculator.calculator_settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'helfi_calculator_calculator_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $settings = $this->getCalculatorSettings();

    $form['#tree'] = TRUE;
    $form['#prefix'] = '<div class="layer-wrapper">';
    $form['#suffix'] = '</div>';

    $calculators = $settings->get('calculator_settings');

    foreach($calculators as $key => $value) {
      $title = ucfirst(str_replace("_", " ", $key));

      $form['calculator_settings'][$key]['active'] = [
        '#type' => 'checkbox',
        '#title' => $this->t($title),
        '#default_value' => $settings->get('calculator_settings')[$key]['active'],
      ];
  
      $form['calculator_settings'][$key]['json'] = [
        '#type' => 'textarea',
        '#title' => $this->t($title),
        '#default_value' => $settings->get('calculator_settings')[$key]['json'],
      ];
    }

    return $form;
  }

  /**
   * Get calculator settings based on current language.
   *
   * @return \Drupal\Core\Config\ImmutableConfig|\Drupal\Core\Config\Config|\Drupal\language\Config\LanguageConfigOverride
   *   Returns calculator settings configuration based on language.
   */
  protected function getCalculatorSettings(): ImmutableConfig|Config|LanguageConfigOverride {
    return $this->config($this->configName);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    // Save calculator settings (active, json) to all languages.
    foreach ($this->languageManager->getLanguages() as $language) {
      $this->saveConfiguration('calculator_settings', $form_state, $language);
    }
  }

  /**
   * Save configuration.
   *
   * @param string $setting
   *   Setting name as a string.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current form state.
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   Language to be handled.
   */
  protected function saveConfiguration(string $setting, FormStateInterface $form_state, LanguageInterface $language) {

    // Check whether the handled language is site default language and
    // save the configuration as default language or translation.
    $settings = ($this->languageManager->getDefaultLanguage()->getId() === $language->getId())
      ? $this->configFactory->getEditable($this->configName)
      : $this->languageManager->getLanguageConfigOverride($language->getId(), $this->configName);

    $settings->set($setting, $form_state->getValue($setting))->save();

    $calculators = $settings->get('calculator_settings');

    $config_factory = \Drupal::configFactory();
    $active_calculators = $config_factory->getEditable('field.storage.paragraph.field_calculator');
    $active_calculators_data = $active_calculators->getRawData();

    $active = [];

    // Get the first element (disabled) from allowed values and keep it
    $disabled = $active_calculators_data['settings']['allowed_values'][0];
    $active[] = $disabled;

    foreach($calculators as $key => $value) {
      if($value['active']) {
        $str = ucfirst(str_replace("_", " ", $key));
        $calculator = [
          'value' => $key,
          'label'=> $str,
        ];
        array_push($active, $calculator);
      }
    };

    // Update calculator paragraph based on active calculators
    $active_calculators_data['settings']['allowed_values'] = $active;
    $active_calculators->setData($active_calculators_data)->save(TRUE);

    // Invalidate caches.
    Cache::invalidateTags($settings->getCacheTags());
  }

}
