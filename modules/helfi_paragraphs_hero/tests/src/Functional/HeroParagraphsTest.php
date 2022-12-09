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
    'helfi_landing_page',
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
    foreach (['fi', 'en', 'sv'] as $langcode) {
      $this->drupalGet('<front>', ['language' => $this->getLanguage($langcode)]);
      $this->assertSession()->statusCodeEquals(200);
      $this->assertSession()->responseHeaderEquals('content-language', $langcode);
    }
  }

}
