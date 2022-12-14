<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_paragraphs_phasing\Functional;

use Drupal\Tests\helfi_platform_config\Functional\ParagraphTestBase;

/**
 * Tests helfi_paragraphs_phasing module.
 *
 * @group helfi_platform_config
 */
class PopularServicesParagraphsTest extends ParagraphTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_paragraphs_popular_services',
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
    $this->assertParagraphTypeEnabled();
  }

  /**
   * {@inheritdoc}
   */
  protected function getParagraphTypes(): array {
    return helfi_paragraphs_popular_services_helfi_paragraph_types();
  }

}
