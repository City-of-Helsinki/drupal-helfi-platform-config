<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_react_search\Unit;

use Drupal\helfi_api_base\Features\FeatureManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\helfi_react_search\LinkedEvents;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @coversDefaultClass \Drupal\helfi_react_search\LinkedEvents
 * @group helfi_react_search
 */
class LinkedEventsTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * Tests Linked Events fixtures.
   *
   * @covers ::getFixturePath
   * @covers ::getFixture
   */
  public function testGetLinkedEventsFixture() : void {
    $path_to_fixture = __DIR__ . '/../../../fixtures/' . LinkedEvents::FIXTURE_NAME . '.json';
    $fixture = file_get_contents($path_to_fixture);

    $featureManager = $this->prophesize(FeatureManagerInterface::class);
    $featureManager
      ->isEnabled(FeatureManagerInterface::USE_MOCK_RESPONSES)
      ->willReturn(TRUE);

    $sut = new LinkedEvents($featureManager->reveal());
    $this->assertJsonStringEqualsJsonString($fixture, json_encode($sut->getFixture()));
  }

}
