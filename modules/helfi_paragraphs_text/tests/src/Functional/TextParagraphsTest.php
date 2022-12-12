<?php

declare(strict_types = 1);

namespace Drupal\Tests\helf_paragraphs_text\Functional;

use Drupal\Tests\helfi_platform_config\Functional\BrowserTestBase;

/**
 * Tests helfi_h module.
 *
 * @group helfi_platform_config
 */
class TextParagraphsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_paragraphs_text',
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
