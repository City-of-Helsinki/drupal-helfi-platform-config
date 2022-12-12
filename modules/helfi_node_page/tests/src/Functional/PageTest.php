<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_node_page\Functional;

use Drupal\Tests\helfi_platform_config\Functional\BrowserTestBase;

/**
 * Tests helfi_node_page module.
 *
 * @group helfi_platform_config
 */
class PageTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_base_content',
    'helfi_node_page',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests  page content type.
   */
  public function testLandingPage() : void {
    $this->assertFrontPageLanguages();

    $this->assertParagraphTypeDisabled('node', 'page', 'field_hero', 'hero');
    // Make sure we can enable paragraphs hero.
    $this->enableModule('helfi_paragraphs_hero');
    // Make sure paragraph type 'hero' is enabled for page.
    $this->assertParagraphTypeEnabled('node', 'page', 'field_hero', 'hero');
  }

}
