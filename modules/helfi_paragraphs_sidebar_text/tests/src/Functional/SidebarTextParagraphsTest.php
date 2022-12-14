<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_paragraphs_sidebar_text\Functional;

use Drupal\Tests\helfi_platform_config\Functional\BrowserTestBase;

/**
 * Tests 'helfi_paragraphs_sidebar_text' module.
 *
 * @group helfi_platform_config
 */
class SidebarTextParagraphsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_paragraphs_sidebar_text',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests paragraph type.
   */
  public function testParagraphs() : void {
    $this->assertFrontPageLanguages();
  }

}
