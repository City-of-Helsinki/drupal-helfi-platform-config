<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_recommendations\Kernel;

use Drupal\helfi_recommendations\Entity\SuggestedTopics;
use Drupal\helfi_recommendations\Entity\SuggestedTopicsInterface;
use Drupal\node\Entity\Node;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Utility\Utility;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests reindexing suggested topics when a parent path alias changes.
 */
#[Group('helfi_recommendations')]
#[RunTestsInSeparateProcesses]
final class PathAliasReindexTest extends AnnifKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'path_alias',
    'search_api_db',
  ];

  /**
   * Index of suggested topics.
   */
  private IndexInterface $topicsIndex;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('path_alias');
    $this->installSchema('search_api', ['search_api_item']);
    $this->installConfig(['search_api']);

    Server::create([
      'id' => 'test',
      'name' => 'Test',
      'status' => TRUE,
      'backend' => 'search_api_db',
      'backend_config' => [
        'database' => 'default:default',
      ],
    ])->save();

    $this->topicsIndex = Index::create([
      'id' => 'suggested_topics_reindex',
      'name' => 'Suggested topics reindex',
      'status' => TRUE,
      'server' => 'test',
      'datasource_settings' => [
        'entity:suggested_topics' => [
          'languages' => [
            'default' => TRUE,
            'selected' => [],
          ],
        ],
      ],
      'tracker_settings' => [
        'default' => [],
      ],
      'options' => [
        'index_directly' => FALSE,
      ],
    ]);
    $this->topicsIndex->save();
  }

  /**
   * Alias changes reindex the parent's suggested topics only.
   */
  public function testPathAliasUpdateReindexesReferencedTopics(): void {
    $unrelated = SuggestedTopics::create();
    $unrelated->save();

    $node = Node::create([
      'type' => 'test_node_bundle',
      'title' => 'Page',
      'test_keywords' => SuggestedTopics::create(),
    ]);
    $node->save();

    $referenced = $node->get('test_keywords')->referencedEntities()[0];
    $this->assertInstanceOf(SuggestedTopicsInterface::class, $referenced);

    $this->markIndexed($this->topicsIndex);
    $this->assertSame([], $this->remainingItemIds($this->topicsIndex));

    $alias = PathAlias::create([
      'path' => '/node/' . $node->id(),
      'alias' => '/original',
      'langcode' => $node->language()->getId(),
    ]);
    $alias->save();
    $this->assertReindexed($referenced, $unrelated);
    $this->markIndexed($this->topicsIndex);

    $alias->setAlias('/updated')->save();
    $this->assertReindexed($referenced, $unrelated);
    $this->markIndexed($this->topicsIndex);

    $alias->delete();
    $this->assertReindexed($referenced, $unrelated);
  }

  /**
   * Asserts that only the given topic is waiting to be indexed.
   */
  private function assertReindexed(SuggestedTopicsInterface $referenced, SuggestedTopicsInterface $unrelated): void {
    $remaining = $this->remainingItemIds($this->topicsIndex);
    $this->assertContains($this->itemId($referenced), $remaining);
    $this->assertNotContains($this->itemId($unrelated), $remaining);
  }

  /**
   * Marks every currently tracked item as indexed.
   */
  private function markIndexed(IndexInterface $index): void {
    $tracker = $index->getTrackerInstance();
    $tracker->trackItemsIndexed($tracker->getRemainingItems());
  }

  /**
   * Returns the item IDs still waiting to be indexed.
   *
   * @return list<string>
   *   Combined Search API item IDs.
   */
  private function remainingItemIds(IndexInterface $index): array {
    return array_values($index->getTrackerInstance()->getRemainingItems());
  }

  /**
   * Returns the Search API item ID for a suggested topic.
   */
  private function itemId(SuggestedTopicsInterface $topic): string {
    return Utility::createCombinedId('entity:suggested_topics', $topic->id() . ':' . $topic->language()->getId());
  }

}
