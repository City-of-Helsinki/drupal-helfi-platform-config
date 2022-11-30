<?php

namespace Drupal\hdbt_admin_tools\Form;

/**
 * @file
 * Contains Drupal\hdbt_admin_tools\Form\SiteSettings.
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
 * Site settings.
 */
class SiteSettings extends ConfigFormBase {

  const SITE_SETTINGS_CONFIGURATION = 'hdbt_admin_tools.site_settings';

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
  protected string $configName = self::SITE_SETTINGS_CONFIGURATION;

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
      'hdbt_admin_tools.site_settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'hdbt_admin_tools_site_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $settings = $this->getSiteSettings();

    $form['#tree'] = TRUE;
    $form['#prefix'] = '<div class="layer-wrapper">';
    $form['#suffix'] = '</div>';

    $form['site_settings'] = [
      '#type' => 'fieldset',
      '#open' => TRUE,
      '#title' => $this->t('Site wide settings'),
    ];

    $form['site_settings']['theme_color'] = [
      '#type' => 'radios',
      '#title' => $this->t('Color palette'),
      '#options' => $this->getColorPalettes(),
      '#required' => TRUE,
      '#description' => $this->t('The chosen color palette will be used site wide in various components.'),
      '#default_value' => $settings->get('site_settings')['theme_color'] ?: [],
    ];

    $icons = [
      'abstract-1' => $this->t('Icon 1'),
      'abstract-2' => $this->t('Icon 2'),
      'abstract-3' => $this->t('Icon 3'),
      'abstract-4' => $this->t('Icon 4'),
      'abstract-5' => $this->t('Icon 5'),
      'abstract-6' => $this->t('Icon 6'),
    ];

    $form['site_settings']['default_icon'] = [
      '#type' => 'radios',
      '#title' => $this->t('Default liftup image'),
      '#options' => $icons,
      '#required' => TRUE,
      '#description' => $this->t('This liftup image will be used site wide if none are provided.'),
      '#default_value' => $settings->get('site_settings')['default_icon'] ?: [],
    ];

    $wave_motifs = [
      'wave' => $this->t('Wave'),
      'vibration' => $this->t('Vibration'),
      'beat' => $this->t('Beat'),
      'pulse' => $this->t('Pulse'),
      'basic' => $this->t('Basic motif'),
      // 'calm' => $this->t('Calm'),
    ];

    $form['site_settings']['koro'] = [
      '#type' => 'radios',
      '#title' => $this->t('Select wave motif'),
      '#options' => $wave_motifs,
      '#required' => TRUE,
      '#description' => $this->t(
        'See wave motifs from <a href=":vig" target="_blank">Visual Identity Guidelines</a>.',
        [':vig' => 'https://brand.hel.fi/en/wave-motifs/']
      ),
      '#default_value' => $settings->get('site_settings')['koro'] ?: [],
    ];

    $form['footer_settings'] = [
      '#type' => 'fieldset',
      '#open' => TRUE,
      '#title' => $this->t('Footer settings'),
    ];

    $form['footer_settings']['footer_color'] = [
      '#type' => 'select',
      '#title' => $this->t('Select footer background color'),
      '#options' => [
        'dark' => $this->t('Dark'),
        'light' => $this->t('Light'),
      ],
      '#default_value' => $settings->get('footer_settings')['footer_color'],
    ];

    $form['#attached']['library'][] = 'hdbt_admin_tools/site_settings';
    $form['#attached']['library'][] = 'hdbt/color-palette';

    return $form;
  }

  /**
   * Get site settings based on current language.
   *
   * @return \Drupal\Core\Config\ImmutableConfig|\Drupal\Core\Config\Config|\Drupal\language\Config\LanguageConfigOverride
   *   Returns site settings configuration based on language.
   */
  protected function getSiteSettings(): ImmutableConfig|Config|LanguageConfigOverride {
    if (
      $this->languageManager->getDefaultLanguage()->getId() !==
      $this->languageManager->getCurrentLanguage()->getId()
    ) {
      return $this->languageManager->getLanguageConfigOverride(
        $this->languageManager->getCurrentLanguage()->getId(),
        $this->configName
      );
    }

    return $this->config($this->configName);
  }

  /**
   * Get color palettes.
   *
   * @return array
   *   Returns color palettes.
   */
  public static function getColorPalettes() {
    return [
      'bus' => t('Bus'),
      'coat-of-arms' => t('Coat of Arms'),
      'copper' => t('Copper'),
      'gold' => t('Gold'),
      'engel' => t('Engel'),
      'metro' => t('Metro'),
      'silver' => t('Silver'),
      'summer' => t('Summer'),
      'suomenlinna' => t('Suomenlinna'),
      'tram' => t('Tram'),
    ];
  }

  /**
   * Provides default value for the color palettes field.
   *
   * @return string
   *   An array of possible key and value options.
   *
   * @see options_allowed_values()
   */
  public static function getColorPaletteDefaultValue() {
    $settings = \Drupal::config(self::SITE_SETTINGS_CONFIGURATION);
    if ($value = $settings?->getOriginal('site_settings.theme_color', FALSE)) {
      return $value;
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    // Save site settings (koro, color and icon) to all languages.
    foreach ($this->languageManager->getLanguages() as $language) {
      $this->saveConfiguration('site_settings', $form_state, $language);
    }

    // Save the footer settings to current language only.
    $this->saveConfiguration('footer_settings', $form_state, $this->languageManager->getCurrentLanguage());
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

    // Invalidate caches.
    Cache::invalidateTags($settings->getCacheTags());
  }

}
