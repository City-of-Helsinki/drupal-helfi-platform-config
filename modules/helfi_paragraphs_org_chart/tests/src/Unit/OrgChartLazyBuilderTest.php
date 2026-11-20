<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_org_chart\Unit;

use Drupal\helfi_api_base\Ahjo\OrganizationType;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_api_base\Features\FeatureManagerInterface;
use Drupal\helfi_paragraphs_org_chart\DTO\OrgChartItem;
use Drupal\helfi_paragraphs_org_chart\OrgChartImporter;
use Drupal\helfi_paragraphs_org_chart\OrgChartLazyBuilder;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * Unit tests for lazy builder.
 *
 * @group helfi_paragraphs_org_chart
 */
class OrgChartLazyBuilderTest extends UnitTestCase {

  use ApiTestTrait;
  use EnvironmentResolverTrait;

  /**
   * Tests lazy builder.
   */
  public function testLazyBuilder() {
    $featureManager = $this->prophesize(FeatureManagerInterface::class);
    $featureManager
      ->isEnabled(FeatureManagerInterface::USE_MOCK_RESPONSES)
      ->willReturn(FALSE);

    $importer = new OrgChartImporter(
      $this->createMockHttpClient([
        new Response(body: '{"id":"00400","title":"test-response","type":2,"url":"https://example.com"}'),
        new ClientException('test-error', new Request('GET', '/test'), new Response()),
      ]),
      $featureManager->reveal(),
      $this->getEnvironmentResolver(Project::ASUMINEN, EnvironmentEnum::Test),
    );
    $sut = new OrgChartLazyBuilder($importer);

    // Successful requests should be cached.
    $response = $sut->build('fi', '00400', 3);
    $this->assertInstanceOf(OrgChartItem::class, $response['#chart']);
    $this->assertEquals('test-response', $response['#chart']->title);
    $this->assertEquals(OrganizationType::BOARD, $response['#chart']->type);
    $this->assertGreaterThan(60, $response['#cache']['max-age'] ?? 0);

    // Exceptions should not be cached.
    $response = $sut->build('fi', '00400', 3);
    $this->assertEquals(['error' => TRUE], $response['#chart']);
    $this->assertLessThanOrEqual(60, $response['#cache']['max-age'] ?? 0);
  }

}
