<?php

declare(strict_types=1);

namespace Drupal\Tests\hdbt_admin_tools\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\hdbt_admin_tools\Form\SiteSettings;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests the SiteSettings form.
 *
 * @group hdbt_admin_tools
 */
class SiteSettingsFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'language',
    'hdbt_admin_tools',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install configs so the form/config system is usable.
    $this->installConfig(['system', 'language', 'hdbt_admin_tools']);

    // Add Swedish so we can test language overrides.
    ConfigurableLanguage::create([
      'id' => 'sv',
      'label' => 'Swedish',
    ])->save();
  }

  /**
   * Tests form submission saves values to the correct language configs.
   */
  public function testFormSubmission(): void {
    // Make Swedish the current interface language for this request.
    $swedish = $this->container->get('entity_type.manager')->getStorage('configurable_language')->load('sv');
    $this->assertNotNull($swedish);
    $this->container->get('language_manager')->setConfigOverrideLanguage($swedish);

    // Build and process the form.
    $form_object = SiteSettings::create($this->container);
    $form_state = new FormState();

    /** @var \Drupal\Core\Form\FormBuilderInterface $form_builder */
    $form_builder = $this->container->get('form_builder');
    $form_id = $form_builder->getFormId($form_object, $form_state);
    $form = $form_builder->retrieveForm($form_id, $form_state);
    $form_builder->prepareForm($form_id, $form, $form_state);
    $form_builder->processForm($form_id, $form, $form_state);

    // Simulate posting values. Note: #tree = TRUE, so use nested arrays.
    $submitted_values = [
      'site_settings' => [
        'theme_color' => 'bus',
        'default_icon' => 'abstract-2',
        'koro' => 'wave',
      ],
      'footer_settings' => [
        'footer_color' => 'dark',
      ],
    ];
    $form_state->setValues($submitted_values);

    // Submit the form (calls submitForm() which writes configs).
    $form_builder->submitForm($form_object, $form_state);

    // Config names / helpers.
    $config_factory = $this->container->get('config.factory');
    $config_name = SiteSettings::SITE_SETTINGS_CONFIGURATION;

    // Default language config (site default, typically 'en').
    $default_config = $config_factory->getEditable($config_name);

    // Swedish language override config.
    /** @var \Drupal\language\ConfigurableLanguageManagerInterface $lang_manager */
    $lang_manager = $this->container->get('language_manager');
    $sv_override = $lang_manager->getLanguageConfigOverride('sv', $config_name);

    $this->assertEquals(
      $submitted_values['site_settings'],
      $default_config->get('site_settings'),
      'Default language config stored site_settings.'
    );
    $this->assertEquals(
      $submitted_values['site_settings'],
      $sv_override->get('site_settings'),
      'Swedish language override stored site_settings.'
    );
    $this->assertEquals(
      $submitted_values['footer_settings'],
      $default_config->get('footer_settings'),
      'Default language config stored footer_settings.'
    );
  }

}
