<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_react_search\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\helfi_react_search\Plugin\DebugDataItem\SearchApiIndex;
use Drupal\search_api\Entity\SearchApiConfigEntityStorage;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\ServerInterface;
use Drupal\search_api\Tracker\TrackerInterface;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests Search api index plugin.
 *
 * @coversDefaultClass \Drupal\helfi_react_search\Plugin\DebugDataItem\SearchApiIndex
 * @group helfi_api_base
 */
class SearchApiIndexItemTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * @covers ::create
   * @covers ::collect
   */
  public function testNoSearchApiStorage() : void {
    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $entityTypeManager->hasDefinition('search_api_index')->willReturn(FALSE);
    $container = new ContainerBuilder();
    $container->set(EntityTypeManagerInterface::class, $entityTypeManager->reveal());
    $sut = SearchApiIndex::create($container, [], '', []);
    $this->assertEmpty($sut->collect());
  }

  /**
   * @covers ::create
   * @covers ::collect
   */
  public function testNoIndex() : void {
    $indexStorage = $this->prophesize(SearchApiConfigEntityStorage::class);
    $indexStorage->loadMultiple()->willReturn([]);
    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $entityTypeManager->hasDefinition('search_api_index')
      ->willReturn(TRUE);
    $entityTypeManager->getStorage('search_api_index')
      ->willReturn($indexStorage->reveal());
    $container = new ContainerBuilder();
    $container->set(EntityTypeManagerInterface::class, $entityTypeManager->reveal());

    $sut = SearchApiIndex::create($container, [], '', []);
    $this->assertEmpty($sut->collect());
  }

  /**
   * @covers ::create
   * @covers ::collect
   * @covers ::resolveResult
   */
  public function testCollect() : void {
    $index1 = $this->prophesize(IndexInterface::class);
    $index1->getOriginalId()->willReturn('index1');
    $index1->getServerInstance()->willThrow(new SearchApiException());

    $server = $this->prophesize(ServerInterface::class);
    $server->isAvailable()->willReturn(TRUE);

    $tracker2 = $this->prophesize(TrackerInterface::class);
    $tracker2->getIndexedItemsCount()->willReturn(0);
    $tracker2->getTotalItemsCount()->willReturn(0);

    $index2 = $this->prophesize(IndexInterface::class);
    $index2->getOriginalId()->willReturn('index2');
    $index2->getServerInstance()->willReturn($server->reveal());
    $index2->getTrackerInstance()->willReturn($tracker2->reveal());

    $tracker3 = $this->prophesize(TrackerInterface::class);
    $tracker3->getIndexedItemsCount()->willReturn(20);
    $tracker3->getTotalItemsCount()->willReturn(20);

    $index3 = $this->prophesize(IndexInterface::class);
    $index3->getOriginalId()->willReturn('index3');
    $index3->getServerInstance()->willReturn($server->reveal());
    $index3->getTrackerInstance()->willReturn($tracker3->reveal());

    $tracker4 = $this->prophesize(TrackerInterface::class);
    $tracker4->getIndexedItemsCount()->willReturn(10);
    $tracker4->getTotalItemsCount()->willReturn(20);
    $index4 = $this->prophesize(IndexInterface::class);
    $index4->getOriginalId()->willReturn('index4');
    $index4->getServerInstance()->willReturn($server->reveal());
    $index4->getTrackerInstance()->willReturn($tracker4->reveal());

    $indexStorage = $this->prophesize(SearchApiConfigEntityStorage::class);
    $indexStorage->loadMultiple()->willReturn([
      $index1->reveal(),
      $index2->reveal(),
      $index3->reveal(),
      $index4->reveal(),
    ]);
    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $entityTypeManager->getStorage('search_api_index')
      ->willReturn($indexStorage->reveal());
    $entityTypeManager->hasDefinition('search_api_index')
      ->willReturn(TRUE);
    $container = new ContainerBuilder();
    $container->set(EntityTypeManagerInterface::class, $entityTypeManager->reveal());

    $sut = SearchApiIndex::create($container, [], '', []);
    $this->assertEquals([
      [
        'id' => 'index1',
        'result' => NULL,
        'status' => NULL,
      ],
      [
        'id' => 'index2',
        'result' => 'indexing or index rebuild required',
        'status' => TRUE,
      ],
      [
        'id' => 'index3',
        'result' => 'Index up to date',
        'status' => TRUE,
      ],
      [
        'id' => 'index4',
        'result' => '10/20',
        'status' => TRUE,
      ],
    ], $sut->collect());
  }

  /**
   * Tests check method.
   */
  public function testCheck() : void {
    $server = $this->prophesize(ServerInterface::class);
    $server->isAvailable()->willReturn(TRUE, FALSE);

    $serverStorage = $this->prophesize(SearchApiConfigEntityStorage::class);
    $serverStorage->loadMultiple()->willReturn([
      $server->reveal(),
    ]);
    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $entityTypeManager->getStorage('search_api_server')
      ->willReturn($serverStorage->reveal());

    $container = new ContainerBuilder();
    $container->set(EntityTypeManagerInterface::class, $entityTypeManager->reveal());

    $sut = SearchApiIndex::create($container, [], '', []);
    $this->assertTrue($sut->check());
    $this->assertFalse($sut->check());
  }

}
