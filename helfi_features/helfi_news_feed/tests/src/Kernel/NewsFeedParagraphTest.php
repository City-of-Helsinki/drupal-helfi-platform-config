<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_news_feed\Kernel;

use Drupal\helfi_news_feed\Entity\NewsFeedParagraph;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Tests NewsFeedParagraph installation.
 *
 * @group helfi_news_feed
 */
class NewsFeedParagraphTest extends KernelTestBase {

  /**
   * Tests that paragraph uses proper bundle class.
   */
  public function testBundleClass() : void {
    $paragraph = Paragraph::create([
      'type' => 'news_list',
      'field_tags' => ['first', 'second'],
      'field_limit' => 22,
      'field_title' => 'test title',
      'field_description' => 'test description',
    ]);
    $paragraph->save();
    $this->assertInstanceOf(NewsFeedParagraph::class, $paragraph);
    $this->assertEquals(['first', 'second'], $paragraph->getTags());
    $this->assertEquals(22, $paragraph->getLimit());
    $this->assertEquals('test title', $paragraph->getTitle());
    $this->assertEquals('test description', $paragraph->getDescription());
  }

}
