<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_paragraphs_phasing\Functional;

use Drupal\Tests\helfi_platform_config\Functional\BrowserTestBase;

/**
 * Tests helfi_paragraphs_phasing module.
 *
 * @group helfi_platform_config
 */
class PhasingParagraphsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_paragraphs_phasing',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests paragraph.
   */
  public function testParagraphs() : void {
    $this->assertFrontPageLanguages();

    // Make sure phasing_item is enabled for phasing paragraph type.
    $this->assertParagraphTypeEnabled('paragraph', 'phasing', ['field_phasing_item'], 'phasing_item');
  }

}
