<?php

declare(strict_types=1);

namespace Drupal\Tests\hdbt_cookie_banner\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\hdbt_cookie_banner\Form\HdbtCookieBannerForm;

/**
 * Tests the HdbtCookieBannerForm form.
 *
 * @group hdbt_cookie_banner
 */
class HdbtCookieBannerFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'hdbt_cookie_banner',
    'helfi_api_base',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'hdbt_cookie_banner']);
  }

  /**
   * Tests form validation for valid and invalid JSON.
   */
  public function testValidateForm(): void {
    $form = HdbtCookieBannerForm::create($this->container);
    $empty_form = [];

    // Test valid JSON in site_settings.
    $valid_form_state = new FormState();
    $valid_form_state->setValues(['site_settings' => '{"key": "value"}']);
    $form->validateForm($empty_form, $valid_form_state);
    $this->assertFalse($valid_form_state->hasAnyErrors(), 'Form validation should pass with valid JSON.');

    // Test invalid JSON in site_settings.
    $invalid_form_state = new FormState();
    $invalid_form_state->setValues(['site_settings' => 'invalid_json']);
    $form->validateForm($empty_form, $invalid_form_state);
    $this->assertTrue($invalid_form_state->hasAnyErrors(), 'Form validation should fail with invalid JSON.');

    // Test empty value in site_settings.
    $valid_form_state = new FormState();
    $valid_form_state->setValues(['site_settings' => '']);
    $form->validateForm($empty_form, $valid_form_state);
    $this->assertFalse($valid_form_state->hasAnyErrors(), 'Form validation should pass with empty value.');
  }

  /**
   * Tests form submission.
   */
  public function testFormSubmission(): void {
    $form_object = HdbtCookieBannerForm::create($this->container);
    $form_state = new FormState();

    // Build and process the form.
    /** @var \Drupal\Core\Form\FormBuilderInterface $form_builder */
    $form_builder = $this->container->get('form_builder');
    $form_id = $form_builder->getFormId($form_object, $form_state);
    $form = $form_builder->retrieveForm($form_id, $form_state);
    $form_builder->prepareForm($form_id, $form, $form_state);
    $form_builder->processForm($form_id, $form, $form_state);

    // Simulate form submission values.
    $form_state->setValues([
      'cookie_information_content' => 'Cookie information test content',
      'cookie_information_title' => 'Cookie information test title',
      'hds_cookie_js_override' => 'url_to_file',
      'site_settings' => '{"key": "value"}',
      'use_custom_settings' => TRUE,
      'use_internal_hds_cookie_js' => TRUE,
    ]);

    // Perform submit (assuming submit handler uses config saving).
    $form_builder->submitForm($form_object, $form_state);

    // Get the saved values from configurations.
    $config_factory = $this->container->get('config.factory');
    $config = $config_factory->getEditable(HdbtCookieBannerForm::SETTINGS);

    // Assert that the config values are correctly saved.
    $user_input = $form_state->getUserInput();
    $this->assertEquals($config->get('cookie_information.content'), $user_input['cookie_information_content']);
    $this->assertEquals($config->get('cookie_information.title'), $user_input['cookie_information_title']);
    $this->assertEquals($config->get('hds_cookie_js_override'), $user_input['hds_cookie_js_override']);
    $this->assertEquals($config->get('site_settings'), $user_input['site_settings']);
    $this->assertTrue($config->get('use_custom_settings'));
    $this->assertTrue($config->get('use_internal_hds_cookie_js'));
  }

}
