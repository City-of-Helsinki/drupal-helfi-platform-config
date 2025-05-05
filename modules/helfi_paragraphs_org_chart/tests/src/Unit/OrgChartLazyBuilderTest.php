<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_org_chart\Unit;

use Drupal\helfi_paragraphs_org_chart\OrgChartImporter;
use Drupal\helfi_paragraphs_org_chart\OrgChartLazyBuilder;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * Unit tests for lazy builder.
 */
class OrgChartLazyBuilderTest extends UnitTestCase {

  use ApiTestTrait;

  /**
   * Tests lazy builder.
   */
  public function testLazyBuilder() {
    $sut = new OrgChartLazyBuilder(new OrgChartImporter($this->createMockHttpClient([
      new Response(body: '["test-response"]'),
      new ClientException('test-error', new Request('GET', '/test'), new Response()),
    ])));

    // Successful requests should be cached.
    $response = $sut->build('fi', '00400', 3);
    $this->assertEquals('test-response', $response['#chart'][0] ?? NULL);
    $this->assertGreaterThan(60, $response['#cache']['max-age'] ?? 0);

    // Exceptions should not be cached.
    $response = $sut->build('fi', '00400', 3);
    $this->assertLessThanOrEqual(60, $response['#cache']['max-age'] ?? 0);
  }

}
