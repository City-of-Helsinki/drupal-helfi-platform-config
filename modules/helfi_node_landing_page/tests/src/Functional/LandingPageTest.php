<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_node_landing_page\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests helfi_node_landing_page module.
 *
 * @group helfi_platform_config
 */
class LandingPageTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_base_content',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Make sure all languages are enabled.
   */
  public function testLanguages() : void {
    foreach (['fi', 'en', 'sv'] as $language) {
      $this->drupalGet('/' . $language);
      $this->assertSession()->statusCodeEquals(200);
      $this->assertSession()->responseHeaderEquals('content-language', $language);
    }
  }

}
