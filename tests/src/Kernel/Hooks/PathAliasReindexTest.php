<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\Hooks;

use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\helfi_platform_config\Kernel\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests reindexing when a path alias changes.
 */
#[Group('helfi_platform_config')]
#[RunTestsInSeparateProcesses]
final class PathAliasReindexTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'path_alias',
    'search_api',
    'search_api_db',
    'system',
    'user',
  ];

  /**
   * Index that includes every node bundle.
   */
  private IndexInterface $contentIndex;

  /**
   * Index that includes only the article bundle.
   */
  private IndexInterface $articleIndex;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('search_api_task');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('search_api', ['search_api_item']);
    $this->installConfig(['search_api']);

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    Server::create([
      'id' => 'test',
      'name' => 'Test',
      'status' => TRUE,
      'backend' => 'search_api_db',
      'backend_config' => [
        'database' => 'default:default',
      ],
    ])->save();

    $this->contentIndex = Index::create([
      'id' => 'content',
      'name' => 'Content',
      'status' => TRUE,
      'server' => 'test',
      'datasource_settings' => [
        'entity:node' => [
          'bundles' => [
            'default' => TRUE,
            'selected' => [],
          ],
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
    $this->contentIndex->save();

    $this->articleIndex = Index::create([
      'id' => 'articles',
      'name' => 'Articles',
      'status' => TRUE,
      'server' => 'test',
      'datasource_settings' => [
        'entity:node' => [
          'bundles' => [
            'default' => FALSE,
            'selected' => ['article'],
          ],
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
    $this->articleIndex->save();
  }

  /**
   * Path alias changes reindex the node on the indexes that include it.
   */
  public function testPathAliasChangesReindexIndexedEntity(): void {
    $node = $this->createNode('page');
    $item_id = $this->itemId($node);
    $this->markIndexed();

    $alias = PathAlias::create([
      'path' => '/node/' . $node->id(),
      'alias' => '/original',
      'langcode' => $node->language()->getId(),
    ]);
    $alias->save();
    $this->assertContains($item_id, $this->remainingItemIds($this->contentIndex));
    $this->assertNotContains($item_id, $this->remainingItemIds($this->articleIndex));
    $this->markIndexed();

    $alias->setAlias('/updated')->save();
    $this->assertContains($item_id, $this->remainingItemIds($this->contentIndex));
    $this->assertNotContains($item_id, $this->remainingItemIds($this->articleIndex));
    $this->markIndexed();

    $alias->delete();
    $this->assertContains($item_id, $this->remainingItemIds($this->contentIndex));
    $this->assertNotContains($item_id, $this->remainingItemIds($this->articleIndex));
  }

  /**
   * Moving an alias to another path reindexes both entities.
   */
  public function testMovedPathAliasReindexesBothEntities(): void {
    $first = $this->createNode('page');
    $second = $this->createNode('page');

    $alias = PathAlias::create([
      'path' => '/node/' . $first->id(),
      'alias' => '/alias',
      'langcode' => $first->language()->getId(),
    ]);
    $alias->save();
    $this->markIndexed();

    $alias->setPath('/node/' . $second->id())->save();

    $remaining = $this->remainingItemIds($this->contentIndex);
    $this->assertContains($this->itemId($first), $remaining);
    $this->assertContains($this->itemId($second), $remaining);
  }

  /**
   * An alias for a route that is not a content entity is ignored.
   */
  public function testNonEntityPathIsIgnored(): void {
    $node = $this->createNode('page');
    $this->markIndexed();

    $alias = PathAlias::create([
      'path' => '/user/login',
      'alias' => '/log-in',
      'langcode' => $node->language()->getId(),
    ]);
    $alias->save();
    $alias->setAlias('/sign-in')->save();

    $this->assertSame([], $this->remainingItemIds($this->contentIndex));
  }

  /**
   * Creates a node.
   */
  private function createNode(string $type): Node {
    $node = Node::create([
      'type' => $type,
      'title' => $this->randomString(),
    ]);
    $node->save();
    return $node;
  }

  /**
   * Returns the Search API item ID of the given node.
   */
  private function itemId(Node $node): string {
    return Utility::createCombinedId('entity:node', $node->id() . ':' . $node->language()->getId());
  }

  /**
   * Marks every currently tracked item as indexed.
   */
  private function markIndexed(): void {
    foreach ([$this->contentIndex, $this->articleIndex] as $index) {
      $tracker = $index->getTrackerInstance();
      $tracker->trackItemsIndexed($tracker->getRemainingItems());
    }
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

}
