<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_org_chart\Unit;

use Drupal\helfi_api_base\Features\FeatureManagerInterface;
use Drupal\helfi_paragraphs_org_chart\OrgChartImporter;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * Tests org chart storage.
 */
class OrgChartImporterTest extends UnitTestCase {

  use ApiTestTrait;

  /**
   * Tests org chart storage.
   */
  public function testStorage(): void {
    $featureManager = $this->prophesize(FeatureManagerInterface::class);
    $featureManager
      ->isEnabled(FeatureManagerInterface::USE_MOCK_RESPONSES)
      ->willReturn(FALSE);

    $importer = new OrgChartImporter($this->createMockHttpClient([
      new ClientException('test-error', new Request('GET', '/test'), new Response()),
      new Response(body: file_get_contents(__DIR__ . '/../../fixtures/org-chart.json')),
    ]), $featureManager->reveal());

    // The api returns an error.
    $response = $importer->fetch('fi', '00400', 3);
    $this->assertArrayHasKey('error', $response);
    $this->assertTrue($response['error']);

    // Empty response is not cached.
    $response = $importer->fetch('fi', '00400', 3);
    $this->assertNotEmpty($response);
    $this->assertArrayNotHasKey('error', $response);
  }

  /**
   * Tests the mock responses.
   */
  public function testMockResponses(): void {
    $featureManager = $this->prophesize(FeatureManagerInterface::class);
    $featureManager
      ->isEnabled(FeatureManagerInterface::USE_MOCK_RESPONSES)
      ->willReturn(TRUE);

    $importer = new OrgChartImporter($this->createMockHttpClient([
      new ClientException('test-error', new Request('GET', '/test'), new Response()),
      new Response(body: file_get_contents(__DIR__ . '/../../fixtures/org-chart-2.json')),
    ]), $featureManager->reveal());

    $response = $importer->fetch('fi', '00000', 2);
    $this->assertNotEmpty($response);
    $this->assertArrayNotHasKey('error', $response);
    $this->assertCount(5, (array) $response['children']);
  }

}
