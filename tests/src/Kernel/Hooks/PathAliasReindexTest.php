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
 * Tests reindexing when a path alias is updated.
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
   * A path alias update reindexes the node on indexes that include it.
   */
  public function testPathAliasUpdateReindexesIndexedEntity(): void {
    $node = Node::create([
      'type' => 'page',
      'title' => 'Page',
    ]);
    $node->save();

    $this->markIndexed($this->contentIndex);
    $this->markIndexed($this->articleIndex);
    $this->assertSame([], $this->remainingItemIds($this->contentIndex));
    $this->assertSame([], $this->remainingItemIds($this->articleIndex));

    $alias = PathAlias::create([
      'path' => '/node/' . $node->id(),
      'alias' => '/original',
      'langcode' => $node->language()->getId(),
    ]);
    $alias->save();
    $this->assertSame([], $this->remainingItemIds($this->contentIndex));

    $alias->setAlias('/updated')->save();

    $item_id = Utility::createCombinedId('entity:node', $node->id() . ':' . $node->language()->getId());
    $this->assertContains($item_id, $this->remainingItemIds($this->contentIndex));
    $this->assertNotContains($item_id, $this->remainingItemIds($this->articleIndex));
  }

  /**
   * An alias for a route that is not a content entity is ignored.
   */
  public function testNonEntityPathIsIgnored(): void {
    $node = Node::create([
      'type' => 'page',
      'title' => 'Page',
    ]);
    $node->save();
    $this->markIndexed($this->contentIndex);

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

}
