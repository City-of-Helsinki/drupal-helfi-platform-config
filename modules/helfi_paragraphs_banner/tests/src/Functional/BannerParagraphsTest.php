<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_paragraphs_banner\Functional;

use Drupal\Tests\helfi_platform_config\Functional\BrowserTestBase;

/**
 * Tests helfi_paragraphs_banner module.
 *
 * @group helfi_platform_config
 */
class BannerParagraphsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_paragraphs_banner',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests banner paragraph.
   */
  public function testParagraphs() : void {
    $this->assertFrontPageLanguages();
  }

}
