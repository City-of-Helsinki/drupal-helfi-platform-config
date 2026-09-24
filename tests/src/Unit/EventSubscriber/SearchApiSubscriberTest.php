<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\EventSubscriber;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\helfi_api_base\Cache\CacheTagInvalidatorInterface;
use Drupal\helfi_platform_config\EventSubscriber\SearchApiSubscriber;
use Drupal\helfi_platform_config\MultisiteContentId;
use Drupal\helfi_platform_config\MultisiteSearch;
use Drupal\search_api\Event\ItemsIndexedEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\IndexInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Search API cache invalidation for the embeddings index.
 */
#[CoversClass(SearchApiSubscriber::class)]
#[Group('helfi_platform_config')]
final class SearchApiSubscriberTest extends UnitTestCase {

  /**
   * Tests that items indexed on the embeddings index are subscribed to.
   */
  public function testGetSubscribedEvents(): void {
    $this->assertSame(
      'onItemsIndexed',
      SearchApiSubscriber::getSubscribedEvents()[SearchApiEvents::ITEMS_INDEXED],
    );
  }

  /**
   * Tests that other indexes do not trigger cache invalidation.
   */
  public function testIgnoresNonEmbeddingsIndex(): void {
    $invalidator = $this->createMock(CacheTagInvalidatorInterface::class);
    $invalidator->expects($this->never())->method('invalidateTags');

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects($this->never())->method('getStorage');

    $this->createSut($invalidator, $this->createMock(MultisiteSearch::class), $entity_type_manager)
      ->onItemsIndexed($this->createEvent('news', ['entity:node/1:en']));
  }

  /**
   * Tests that multisite index ids are prefixed before loading.
   */
  public function testInvalidatesPrefixedIdsOnMultisiteIndex(): void {
    $processed_id = 'entity:node/1:en';
    $prefixed_id = 'site_etusivu/' . $processed_id;

    $search = $this->createMock(MultisiteSearch::class);
    $search->method('isMultisiteIndex')->with('embeddings')->willReturn(TRUE);
    $search->expects($this->once())
      ->method('addPrefixToId')
      ->with($processed_id)
      ->willReturn($prefixed_id);

    $entity = $this->createEntity(['helfi_multisite_content:' . $prefixed_id]);
    $this->createSut(
      $this->expectInvalidatedTags(['helfi_multisite_content:' . $prefixed_id]),
      $search,
      $this->createEntityTypeManager([$prefixed_id], [$prefixed_id => $entity]),
    )->onItemsIndexed($this->createEvent('embeddings', [$processed_id]));
  }

  /**
   * Tests that cache tags from loaded entities are merged uniquely.
   */
  public function testMergesCacheTagsFromLoadedEntities(): void {
    $search = $this->createMock(MultisiteSearch::class);
    $search->method('isMultisiteIndex')->willReturn(FALSE);

    $first = $this->createEntity([
      'helfi_multisite_content:1' => 'helfi_multisite_content:1',
      'shared' => 'shared',
    ]);
    $second = $this->createEntity(['helfi_multisite_content:2', 'shared']);

    $this->createSut(
      $this->expectInvalidatedTags([
        'helfi_multisite_content:1',
        'shared',
        'helfi_multisite_content:2',
      ]),
      $search,
      $this->createEntityTypeManager(
        ['id-1', 'id-2'],
        ['id-1' => $first, 'id-2' => $second],
      ),
    )->onItemsIndexed($this->createEvent('embeddings', ['id-1', 'id-2']));
  }

  /**
   * Tests that missing entities and empty tag lists do not invalidate.
   */
  public function testDoesNotInvalidateWhenNoCacheTags(): void {
    $invalidator = $this->createMock(CacheTagInvalidatorInterface::class);
    $invalidator->expects($this->never())->method('invalidateTags');

    $search = $this->createMock(MultisiteSearch::class);
    $search->method('isMultisiteIndex')->willReturn(FALSE);

    $this->createSut(
      $invalidator,
      $search,
      $this->createEntityTypeManager(['missing'], ['missing' => new \stdClass()]),
    )->onItemsIndexed($this->createEvent('embeddings', ['missing']));
  }

  /**
   * Creates the subscriber.
   */
  private function createSut(
    CacheTagInvalidatorInterface $invalidator,
    MultisiteSearch $search,
    EntityTypeManagerInterface $entity_type_manager,
  ): SearchApiSubscriber {
    return new SearchApiSubscriber($invalidator, $search, $entity_type_manager);
  }

  /**
   * Creates an items-indexed event.
   *
   * Search API documents processed IDs as int[], but the embeddings index uses
   * string item IDs. The constructor is typed only as array at runtime.
   *
   * @phpstan-param array<int|string> $processed_ids
   *   Processed Search API item ids.
   */
  private function createEvent(string $index_id, array $processed_ids): ItemsIndexedEvent {
    $index = $this->createMock(IndexInterface::class);
    $index->method('id')->willReturn($index_id);
    $event = new ItemsIndexedEvent($index, []);
    $reflection = new \ReflectionProperty(ItemsIndexedEvent::class, 'processedIds');
    $reflection->setValue($event, $processed_ids);
    return $event;
  }

  /**
   * Creates entity type manager that loads the given entities.
   *
   * @param list<string> $ids
   *   Ids expected to be passed to loadMultiple().
   * @param array<string, object> $entities
   *   Entities keyed by id.
   */
  private function createEntityTypeManager(array $ids, array $entities): EntityTypeManagerInterface {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())
      ->method('loadMultiple')
      ->with($ids)
      ->willReturn($entities);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects($this->once())
      ->method('getStorage')
      ->with(MultisiteContentId::ENTITY_TYPE_ID)
      ->willReturn($storage);

    return $entity_type_manager;
  }

  /**
   * Creates an entity that reports the given cache tags.
   *
   * @param list<string>|array<string, string> $tags
   *   Tags returned by getCacheTagsToInvalidate().
   */
  private function createEntity(array $tags): EntityInterface {
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('getCacheTagsToInvalidate')->willReturn($tags);
    return $entity;
  }

  /**
   * Creates an invalidator that must receive the given tags.
   *
   * @param list<string> $tags
   *   Expected tags.
   */
  private function expectInvalidatedTags(array $tags): CacheTagInvalidatorInterface {
    $invalidator = $this->createMock(CacheTagInvalidatorInterface::class);
    $invalidator->expects($this->once())
      ->method('invalidateTags')
      ->with($tags);
    return $invalidator;
  }

}
