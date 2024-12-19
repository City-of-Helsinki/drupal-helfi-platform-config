<?php

declare(strict_types=1);

namespace Drupal\helfi_calculator\Form;

/**
 * @file
 * Contains Drupal\helfi_calculator\Form\CalculatorSettings.
 */

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\language\Config\LanguageConfigOverride;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Calculator settings.
 */
class CalculatorSettings extends ConfigFormBase {

  const CALCULATOR_SETTINGS_CONFIGURATION = 'helfi_calculator.settings';

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
  public static function create(ContainerInterface $container) : self {
    return new self(
      $container->get('config.factory'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'helfi_calculator.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'helfi_calculator_calculator_settings';
  }

  /**
   * Get translated calculator label value from configuration.
   *
   * @param string $calculator
   *   Calculator machine name.
   *
   * @return string
   *   Returns translated calculator label or original label if there is no
   *   translation.
   */
  protected function getCalculatorLabel(string $calculator): string {
    if (
      $this->languageManager->getDefaultLanguage()->getId() !==
      $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_INTERFACE)->getId()
    ) {
      $configuration = $this->languageManager->getLanguageConfigOverride(
        $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_INTERFACE)->getId(),
        'helfi_calculator.settings'
      );
      if (
        !empty($configuration->get('calculators')) &&
        array_key_exists('label', $configuration->get('calculators')[$calculator])
      ) {
        return $configuration->get('calculators')[$calculator]['label'];
      }
    }
    if (array_key_exists('label', $this->config('helfi_calculator.settings')->get('calculators')[$calculator])) {
      return $this->config('helfi_calculator.settings')->get('calculators')[$calculator]['label'];
    }

    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $settings = $this->getCalculatorSettings();

    $form['#tree'] = TRUE;
    $form['#prefix'] = '<div class="layer-wrapper"><h2>' . $this->t('Available calculators') . '</h2>';
    $form['#suffix'] = '</div>';

    $calculators = $settings->get('calculators');

    foreach ($calculators as $key => $value) {
      $form['calculators'][$key] = [
        '#type' => 'details',
        '#title' => $this->getCalculatorLabel($key),
        '#open' => $settings->get('calculators')[$key]['active'],
      ];

      $form['calculators'][$key]['active'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('On/off'),
        '#default_value' => $settings->get('calculators')[$key]['active'],
      ];

      $form['calculators'][$key]['json'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Calculator data'),
        '#default_value' => $settings->get('calculators')[$key]['json'],
      ];
    }

    return $form;
  }

  /**
   * Get calculator settings.
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

    $calculators = $this->configFactory->getEditable($this->configName)->get('calculators');

    foreach ($calculators as $machine_name => &$calculator) {
      $calculator['active'] = (bool) $form_state->getValue('calculators')[$machine_name]['active'];
      $calculator['json'] = $form_state->getValue('calculators')[$machine_name]['json'];
    }

    $settings = $this->configFactory->getEditable($this->configName);
    $settings->set('calculators', $calculators)->save();
  }

  /**
   * Get active calculators list.
   *
   * @return array
   *   Returns active calculators list.
   */
  public static function getActiveCalculators(): array {
    /** @var \Drupal\language\ConfigurableLanguageManagerInterface $language_manager */
    $language_manager = \Drupal::languageManager();
    $calculator_settings = \Drupal::config('helfi_calculator.settings')
      ->get('calculators');

    $allowed_values = [
      'disabled' => t('Disabled'),
    ];

    foreach ($calculator_settings as $machine_name => $configuration) {
      if ($configuration['active']) {
        $calculator_label = $machine_name;

        if (array_key_exists('label', $calculator_settings[$machine_name])) {
          $calculator_label = $calculator_settings[$machine_name]['label'];
        }

        if (
          $language_manager->getDefaultLanguage()->getId() !==
          $language_manager->getCurrentLanguage(LanguageInterface::TYPE_INTERFACE)->getId()
        ) {
          $configuration = $language_manager->getLanguageConfigOverride(
            $language_manager->getCurrentLanguage(LanguageInterface::TYPE_INTERFACE)->getId(),
            'helfi_calculator.settings'
          );
          if (
            isset($configuration->get('calculators')[$machine_name]) &&
            array_key_exists('label', $configuration->get('calculators')[$machine_name])
          ) {
            $calculator_label = $configuration->get('calculators')[$machine_name]['label'];
          }
        }

        $allowed_values[$machine_name] = $calculator_label;
      }
    }
    return $allowed_values;
  }

}
