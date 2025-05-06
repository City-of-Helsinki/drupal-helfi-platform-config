<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_news_list\Kernel\Entity;

use Drupal\helfi_paragraphs_news_list\Entity\NewsFeedParagraph;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Tests NewsFeedParagraph installation.
 *
 * @group helfi_paragraphs_news_list
 */
class NewsFeedParagraphTest extends EntityKernelTestBase {

  /**
   * Tests that paragraph uses proper bundle class.
   */
  public function testBundleClass() : void {
    $paragraph = Paragraph::create([
      'type' => 'news_list',
      'field_helfi_news_neighbourhoods' => [$this->neighbourhood],
      'field_helfi_news_tags' => [$this->tag],
      'field_helfi_news_groups' => [$this->group],
      'field_limit' => 22,
      'field_news_list_title' => 'test title',
      'field_news_list_description' => 'test description',
    ]);
    $paragraph->save();

    $this->assertInstanceOf(NewsFeedParagraph::class, $paragraph);
    $this->assertEquals([['target_id' => 'ff10dbf0-6b00-400b-a8a9-4fae102ea92c:fi']], $paragraph->getNeighbourhoods());
    $this->assertEquals(['ff10dbf0-6b00-400b-a8a9-4fae102ea92c'], $paragraph->getNeighbourhoodsUuids());
    $this->assertEquals([['target_id' => 'ca9a7c2e-acbb-4f03-938b-9cd86fd606ac:fi']], $paragraph->getTags());
    $this->assertEquals(['ca9a7c2e-acbb-4f03-938b-9cd86fd606ac'], $paragraph->getTagsUuid());
    $this->assertEquals([['target_id' => 'e30fa7be-4d13-4216-8658-103fb9a26c8c:fi']], $paragraph->getGroups());
    $this->assertEquals(['e30fa7be-4d13-4216-8658-103fb9a26c8c'], $paragraph->getGroupsUuid());
    $this->assertEquals(22, $paragraph->getLimit());
    $this->assertEquals('test title', $paragraph->getTitle());
    $this->assertEquals('test description', $paragraph->getDescription());
  }

}
