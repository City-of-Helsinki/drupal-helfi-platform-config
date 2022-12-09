<?php

declare(strict_types = 1);

namespace Drupal\Tests\helf_paragraphs_hero\Functional;

use Drupal\Tests\helfi_platform_config\Functional\BrowserTestBase;

/**
 * Tests helfi_h module.
 *
 * @group helfi_platform_config
 */
class HeroParagraphsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_paragraphs_hero',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests hero paragraph.
   */
  public function testParagraphs() : void {
    $this->assertFrontPageLanguages();
  }

}
