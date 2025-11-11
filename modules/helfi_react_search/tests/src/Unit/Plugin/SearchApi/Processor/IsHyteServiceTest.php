<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_react_search\Unit\Plugin\SearchApi\Processor;

use Drupal\helfi_tpr\Entity\Service;
use Drupal\helfi_react_search\Plugin\search_api\processor\IsHyteService;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Drupal\search_api\Item\ItemInterface;
use Drupal\Core\TypedData\ComplexDataInterface;

/**
 * Tests the "Hyte service filter" processor.
 *
 * @group helfi_react_search
 */
class IsHyteServiceTest extends ServiceTestBase {

  use ProphecyTrait;

  /**
   * The name_synonyms field mock.
   */
  private \stdClass $nameSynonyms;

  /**
   * The item mock.
   */
  private ObjectProphecy $item;

  /**
   * Creates a new processor object for use in the tests.
   */
  protected function setUp(): void {
    parent::setUp();

    $this->nameSynonyms = new \stdClass();
    $this->nameSynonyms->value = 'test';

    $service = $this->prophesize(Service::class);
    $service->get('name_synonyms')->willReturn($this->nameSynonyms);

    $originalObject = $this->prophesize(ComplexDataInterface::class);
    $originalObject->getValue()->willReturn($service->reveal());

    $this->item = $this->prophesize(ItemInterface::class);
    $this->item->getOriginalObject()->willReturn($originalObject->reveal());

    $this->processor = new IsHyteService([], 'is_hyte_service', []);
  }

  /**
   * Tests alterIndexedItems() with a non-Hyte service.
   */
  public function testAlterIndexedItems() {
    $items = [$this->item->reveal()];
    $this->processor->alterIndexedItems($items);
    $this->assertCount(0, $items);
  }

  /**
   * Tests alterIndexedItems() with a Hyte service.
   */
  public function testAlterIndexedItemsHyteService() {
    $this->nameSynonyms->value = 'hh_mie';
    $items = [$this->item->reveal()];
    $this->processor->alterIndexedItems($items);
    $this->assertCount(1, $items);
  }

}
