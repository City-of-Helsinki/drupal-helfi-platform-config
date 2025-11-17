<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_react_search\Unit\Plugin\SearchApi\Processor;

use Drupal\helfi_react_search\Plugin\search_api\processor\ChannelsForService;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Tests the "Channels for service" processor.
 *
 * @group helfi_react_search
 */
class ChannelsForServiceTest extends ServiceTestBase {

  /**
   * Creates a new processor object for use in the tests.
   */
  protected function setUp(): void {
    parent::setUp();

    $this->processor = new ChannelsForService([], 'channels_for_service', []);
  }

  /**
   * Tests getPropertyDefinitions().
   */
  public function testGetPropertyDefinitions() {
    $properties = $this->processor->getPropertyDefinitions();
    $this->assertArrayHasKey('channels_for_service', $properties);
    $this->assertInstanceOf(ProcessorProperty::class, $properties['channels_for_service']);
  }

}
