<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_react_search\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\helfi_react_search\Plugin\DebugDataItem\SearchApiIndex;
use Drupal\search_api\Entity\SearchApiConfigEntityStorage;
use Drupal\search_api\ServerInterface;
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
   * Tests check method.
   */
  public function testCheck() : void {
    $server = $this->prophesize(ServerInterface::class);
    $server->status()
      ->shouldBeCalled()
      ->willReturn(TRUE);
    $server->isAvailable()
      ->shouldBeCalled()
      ->willReturn(TRUE, FALSE);

    $disabledServer = $this->prophesize(ServerInterface::class);
    $disabledServer->status()
      ->shouldBeCalled()
      ->willReturn(FALSE);

    $serverStorage = $this->prophesize(SearchApiConfigEntityStorage::class);
    $serverStorage->loadMultiple()->willReturn([
      $server->reveal(),
      $disabledServer->reveal(),
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
