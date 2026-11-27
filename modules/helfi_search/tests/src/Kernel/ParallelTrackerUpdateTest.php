<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Kernel;

use Drupal\entity_test\Entity\EntityTestMulRevChanged;
use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\IndexInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests switching the embeddings index to the parallel tracker.
 */
#[Group('helfi_search')]
#[RunTestsInSeparateProcesses]
class ParallelTrackerUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'entity_test',
    'search_api',
    'search_api_test',
    'helfi_platform_config',
    'config_rewrite',
    'diff',
    'helfi_api_base',
    'helfi_search',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('search_api', ['search_api_item']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test_mulrev_changed');
    $this->installEntitySchema('search_api_task');
    $this->installConfig('search_api');

    Server::create([
      'id' => 'server',
      'name' => 'Server',
      'backend' => 'search_api_test',
    ])->save();
    Index::create([
      'id' => 'embeddings',
      'name' => 'Embeddings',
      'server' => 'server',
      'datasource_settings' => [
        'entity:entity_test_mulrev_changed' => [],
      ],
      'tracker_settings' => [
        'default' => ['indexing_order' => 'lifo'],
      ],
      'options' => ['index_directly' => FALSE],
    ])->save();

    \Drupal::moduleHandler()->loadInclude('helfi_search', 'install');
  }

  /**
   * Loads the embeddings index without the static cache.
   */
  private function loadIndex(): IndexInterface {
    $storage = \Drupal::entityTypeManager()->getStorage('search_api_index');
    $storage->resetCache();
    $index = $storage->load('embeddings');
    $this->assertInstanceOf(IndexInterface::class, $index);
    return $index;
  }

  /**
   * Tests that the tracker is switched without re-tracking the items.
   */
  public function testTrackerIsSwitchedWithoutReindexing(): void {
    for ($i = 0; $i < 3; $i++) {
      EntityTestMulRevChanged::create(['name' => "Item $i"])->save();
    }
    $this->assertEquals(3, $this->loadIndex()->indexItems());

    helfi_search_update_11028();

    $index = $this->loadIndex();
    $this->assertEquals('parallel', $index->getTrackerId());
    $this->assertEquals([
      'indexing_order' => 'lifo',
      'claim_timeout' => 60,
    ], $index->getTrackerInstance()->getConfiguration());
    $this->assertEquals(3, $index->getTrackerInstance()->getTotalItemsCount());
    $this->assertEquals(3, $index->getTrackerInstance()->getIndexedItemsCount());
    $this->assertEquals(0, $index->getTrackerInstance()->getRemainingItemsCount());

    // An index that doesn't use the basic tracker is left alone.
    \Drupal::configFactory()->getEditable('search_api.index.embeddings')
      ->set('tracker_settings.parallel.claim_timeout', 120)
      ->save();
    helfi_search_update_11028();
    $this->assertEquals([
      'parallel' => [
        'indexing_order' => 'lifo',
        'claim_timeout' => 120,
      ],
    ], \Drupal::config('search_api.index.embeddings')->get('tracker_settings'));
  }

}
