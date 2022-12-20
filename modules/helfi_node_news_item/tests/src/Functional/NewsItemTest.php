<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_node_news_item\Functional;

use Drupal\Tests\helfi_platform_config\Functional\ParagraphTestBase;

/**
 * Tests helfi_node_news_item module.
 *
 * @group helfi_platform_config
 */
class NewsItemTest extends ParagraphTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_base_content',
    'helfi_node_news_item',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests news_item content type.
   */
  public function testDefault() : void {
    $this->assertFrontPageLanguages();
    $this->assertParagraphTypeEnabled();
  }

  /**
   * {@inheritdoc}
   */
  protected function getParagraphTypes(): array {
    return helfi_node_news_item_helfi_paragraph_types();
  }

}
