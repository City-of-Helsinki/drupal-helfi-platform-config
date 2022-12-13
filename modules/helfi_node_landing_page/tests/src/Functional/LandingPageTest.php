<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_node_landing_page\Functional;

use Drupal\Tests\helfi_platform_config\Functional\BrowserTestBase;

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
    'helfi_node_landing_page',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests landing page content type.
   */
  public function testLandingPage() : void {
    $this->assertFrontPageLanguages();

    $this->assertParagraphTypeDisabled('node', 'landing_page', ['field_hero'], 'hero');
    // Make sure we can enable paragraphs hero.
    $this->enableModule('helfi_paragraphs_hero');
    // Make sure paragraph type 'hero' is enabled for landing page.
    $this->assertParagraphTypeEnabled('node', 'landing_page', ['field_hero'], 'hero');
  }

}
