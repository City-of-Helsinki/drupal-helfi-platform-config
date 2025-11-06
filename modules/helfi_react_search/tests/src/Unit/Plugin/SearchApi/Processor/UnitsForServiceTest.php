<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_react_search\Unit\Plugin\SearchApi\Processor;

use Drupal\helfi_react_search\Plugin\search_api\processor\UnitsForService;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Tests the "Units for service" processor.
 *
 * @group helfi_react_search
 */
class UnitsForServiceTest extends ServiceTestBase {

  /**
   * Creates a new processor object for use in the tests.
   */
  protected function setUp(): void {
    parent::setUp();

    $this->processor = new UnitsForService([], 'units_for_service', []);
  }

  /**
   * Tests getPropertyDefinitions().
   */
  public function testGetPropertyDefinitions() {
    $properties = $this->processor->getPropertyDefinitions();
    $this->assertArrayHasKey('units_for_service', $properties);
    $this->assertInstanceOf(ProcessorProperty::class, $properties['units_for_service']);
  }

}
