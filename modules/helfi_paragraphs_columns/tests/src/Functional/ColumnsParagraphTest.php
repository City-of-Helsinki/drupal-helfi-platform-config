<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_paragraphs_columns\Functional;

use Drupal\Tests\helfi_platform_config\Functional\ParagraphTestBase;

/**
 * Tests helfi_paragraphs_columns module.
 *
 * @group helfi_platform_config
 */
class ColumnsParagraphTest extends ParagraphTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_paragraphs_columns',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests columns paragraph.
   */
  public function testParagraphs() : void {
    $this->assertFrontPageLanguages();
    $this->assertParagraphTypeEnabled();
  }

  /**
   * {@inheritdoc}
   */
  protected function getParagraphTypes(): array {
    return helfi_paragraphs_columns_helfi_paragraph_types();
  }

}
