<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_paragraphs_target_group_links\Functional;

use Drupal\Tests\helfi_platform_config\Functional\ParagraphTestBase;

/**
 * Tests helfi_paragraphs_target_group_links module.
 *
 * @group helfi_platform_config
 */
class TargetGroupLinksTest extends ParagraphTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_paragraphs_target_group_links',
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
    $this->assertParagraphTypeEnabled();
  }

  /**
   * {@inheritdoc}
   */
  protected function getParagraphTypes(): array {
    return helfi_paragraphs_target_group_links_helfi_paragraph_types();
  }

}
