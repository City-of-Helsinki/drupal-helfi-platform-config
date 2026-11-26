<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Traits;

use Drupal\entity_test\Entity\EntityTestMulRevChanged;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\IndexInterface;

/**
 * Search API indexes for shared index kernel tests.
 *
 * Requires the search_api, search_api_test, entity_test, language and user
 * modules.
 */
trait SharedSearchIndexTrait {

  /**
   * Installs Search API and creates a test server.
   */
  protected function setUpSearchApi(): void {
    $this->installSchema('search_api', ['search_api_item']);
    $this->installEntitySchema('entity_test_mulrev_changed');
    $this->installEntitySchema('search_api_task');
    $this->installEntitySchema('user');
    $this->installConfig('search_api');

    Server::create([
      'id' => 'test_server',
      'name' => 'Test server',
      'status' => TRUE,
      'backend' => 'search_api_test',
    ])->save();
  }

  /**
   * Creates an index that tracks entity_test entities.
   */
  protected function createIndex(string $id, bool $enabled = TRUE, string $server = 'test_server'): IndexInterface {
    $index = Index::create([
      'id' => $id,
      'name' => $id,
      'status' => $enabled,
      'server' => $server,
      'datasource_settings' => [
        'entity:entity_test_mulrev_changed' => [],
      ],
      'tracker_settings' => [
        'default' => [],
      ],
      'options' => [
        'index_directly' => FALSE,
      ],
    ]);
    $index->save();
    return $index;
  }

  /**
   * Creates entities and marks them indexed on every enabled index.
   */
  protected function createIndexedItems(int $count): void {
    for ($i = 1; $i <= $count; $i++) {
      EntityTestMulRevChanged::create(['name' => "Item $i"])->save();
    }
    foreach (Index::loadMultiple() as $index) {
      if ($index->status()) {
        $tracker = $index->getTrackerInstance();
        $tracker->trackItemsIndexed($tracker->getRemainingItems());
        // Index::indexSpecificItems() resets this after indexing, so a
        // later reindex() marks the items again.
        $this->container->get('state')->set("search_api.index.{$index->id()}.has_reindexed", FALSE);
      }
    }
  }

  /**
   * The number of items waiting to be indexed.
   */
  protected function remainingItems(string $indexId): int {
    $index = Index::load($indexId);
    $this->assertInstanceOf(IndexInterface::class, $index);
    return $index->getTrackerInstance()->getRemainingItemsCount();
  }

}
