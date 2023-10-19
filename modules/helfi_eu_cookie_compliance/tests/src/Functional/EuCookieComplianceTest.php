<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_eu_cookie_compliance\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests helfi_eu_cookie_compliance module.
 *
 * @group helfi_platform_config
 */
class EuCookieComplianceTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'helfi_api_base',
    'helfi_user_roles',
    'helfi_eu_cookie_compliance',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Make sure the cookie intro form page loads.
   */
  public function testCookieIntroFormPage() : void {
    $this->drupalGet(Url::fromRoute('helfi_eu_cookie_compliance.cookie_consent_intro_form'));
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->createUser(['access administration pages']));

    $this->drupalGet(Url::fromRoute('helfi_eu_cookie_compliance.cookie_consent_intro_form'));
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Make sure the cookie consent advanced settings block gets installed.
   */
  public function testCookieConsentBlock() : void {
    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('Consent management');

    $this->drupalGet(Url::fromRoute('helfi_eu_cookie_compliance.cookie_consent'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Consent management');
  }

}
