<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_news_list\Kernel\Entity;

use Drupal\Tests\helfi_paragraphs_news_list\Kernel\KernelTestBase;
use Drupal\external_entities\ExternalEntityStorage;
use Drupal\helfi_paragraphs_news_list\Entity\NewsFeedParagraph;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Tests NewsFeedParagraph installation.
 *
 * @group helfi_paragraphs_news_list
 */
class NewsFeedParagraphTest extends KernelTestBase {

  /**
   * Gets the storage for given entity type.
   *
   * @param string $entityType
   *   The entity type.
   *
   * @return \Drupal\external_entities\ExternalEntityStorage
   *   The storage.
   */
  private function getStorage(string $entityType) : ExternalEntityStorage {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage($entityType);
    assert($storage instanceof ExternalEntityStorage);

    return $storage;
  }

  /**
   * Tests that paragraph uses proper bundle class.
   */
  public function testBundleClass() : void {
    $neighbourhood_uuid = 'ff10dbf0-6b00-400b-a8a9-4fae102ea92c';
    $neighbourhood_id = $neighbourhood_uuid . ':fi';
    $neighbourhood = $this->getStorage('helfi_news_neighbourhoods')->create([
      'id' => $neighbourhood_id,
      'title' => 'Neighbourhood',
    ]);
    $tags_uuid = 'ca9a7c2e-acbb-4f03-938b-9cd86fd606ac';
    $tags_id = $tags_uuid . ':fi';
    $tags = $this->getStorage('helfi_news_tags')->create([
      'id' => $tags_id,
      'title' => 'Tags',
    ]);
    $groups_uuid = 'e30fa7be-4d13-4216-8658-103fb9a26c8c';
    $groups_id = $groups_uuid . ':fi';
    $groups = $this->getStorage('helfi_news_groups')->create([
      'id' => $groups_id,
      'title' => 'Tags',
    ]);

    $paragraph = Paragraph::create([
      'type' => 'news_list',
      'field_helfi_news_neighbourhoods' => [$neighbourhood],
      'field_helfi_news_tags' => [$tags],
      'field_helfi_news_groups' => [$groups],
      'field_limit' => 22,
      'field_news_list_title' => 'test title',
      'field_news_list_description' => 'test description',
    ]);
    $paragraph->save();

    $this->assertInstanceOf(NewsFeedParagraph::class, $paragraph);
    $this->assertEquals([['target_id' => $neighbourhood_id]], $paragraph->getNeighbourhoods());
    $this->assertEquals([$neighbourhood_uuid], $paragraph->getNeighbourhoodsUuids());
    $this->assertEquals([['target_id' => $tags_id]], $paragraph->getTags());
    $this->assertEquals([$tags_uuid], $paragraph->getTagsUuid());
    $this->assertEquals([['target_id' => $groups_id]], $paragraph->getGroups());
    $this->assertEquals([$groups_uuid], $paragraph->getGroupsUuid());
    $this->assertEquals(22, $paragraph->getLimit());
    $this->assertEquals('test title', $paragraph->getTitle());
    $this->assertEquals('test description', $paragraph->getDescription());
  }

}
