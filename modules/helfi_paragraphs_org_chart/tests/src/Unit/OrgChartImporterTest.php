<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_org_chart\Unit;

use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_api_base\Features\FeatureManagerInterface;
use Drupal\helfi_paragraphs_org_chart\OrgChartImporter;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * Tests org chart storage.
 *
 * @group helfi_paragraphs_org_chart
 */
class OrgChartImporterTest extends UnitTestCase {

  use ApiTestTrait;
  use EnvironmentResolverTrait;

  /**
   * Tests org chart storage.
   */
  public function testStorage(): void {
    $importer = $this->getSut([
      new ClientException('test-error', new Request('GET', '/test'), new Response()),
      new Response(body: file_get_contents(__DIR__ . '/../../fixtures/org-chart.json')),
    ]);

    // The api returns an error.
    $response = $importer->fetch('fi', '00400', 3);
    $this->assertEmpty($response);

    // Empty response is not cached.
    $response = $importer->fetch('fi', '00400', 3);
    $this->assertNotEmpty($response);
  }

  /**
   * Tests the mock responses.
   */
  public function testMockResponses(): void {
    $featureManager = $this->prophesize(FeatureManagerInterface::class);
    $featureManager
      ->isEnabled(FeatureManagerInterface::USE_MOCK_RESPONSES)
      ->willReturn(TRUE);

    $importer = $this->getSut([], $featureManager->reveal());

    $response = $importer->fetch('fi', '00000', 2);
    $this->assertNotEmpty($response);
    $this->assertCount(5, (array) $response['children']);
  }

  /**
   * Gets service under test.
   */
  private function getSut(array $responses, ?FeatureManagerInterface $featureManager = NULL): OrgChartImporter {
    if ($featureManager === NULL) {
      $featureManagerMock = $this->prophesize(FeatureManagerInterface::class);
      $featureManagerMock
        ->isEnabled(FeatureManagerInterface::USE_MOCK_RESPONSES)
        ->willReturn(FALSE);

      $featureManager = $featureManagerMock->reveal();
    }

    return new OrgChartImporter(
      $this->createMockHttpClient($responses),
      $featureManager,
      $this->getEnvironmentResolver(Project::ASUMINEN, EnvironmentEnum::Test),
    );
  }

}
