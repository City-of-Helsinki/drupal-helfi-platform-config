<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_node_page\Functional;

use Drupal\Tests\helfi_platform_config\Functional\ParagraphTestBase;

/**
 * Tests helfi_node_page module.
 *
 * @group helfi_platform_config
 */
class PageTest extends ParagraphTestBase {

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
   * Tests page content type.
   */
  public function testDefault() : void {
    $this->assertFrontPageLanguages();
    $this->assertParagraphTypeEnabled();
  }

  /**
   * {@inheritdoc}
   */
  protected function getParagraphTypes(): array {
    return helfi_node_page_helfi_paragraph_types();
  }

}
