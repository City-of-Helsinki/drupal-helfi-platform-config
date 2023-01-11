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
    'helfi_eu_cookie_compliance',
    'helfi_api_base',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public function setUp() : void {
    parent::setUp();
    helfi_eu_cookie_compliance_generate_blocks($this->defaultTheme, 'content', TRUE);
  }

  /**
   * Make sure the cookie intro from page loads.
   */
  public function testCookieIntroFromPage() : void {
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
    $this->assertSession()->pageTextNotContains('Eu Cookie Compliance Block');

    $this->drupalGet(Url::fromRoute('helfi_eu_cookie_compliance.cookie_consent'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Eu Cookie Compliance Block');
  }

}
