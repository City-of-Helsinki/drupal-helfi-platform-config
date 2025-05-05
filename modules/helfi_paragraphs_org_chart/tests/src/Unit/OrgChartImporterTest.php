<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_org_chart\Unit;

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
    $importer = new OrgChartImporter($this->createMockHttpClient([
      new ClientException('test-error', new Request('GET', '/test'), new Response()),
      new Response(body: file_get_contents(__DIR__ . '/../../fixtures/org-chart.json')),
    ]));

    // The api returns an error.
    $response = $importer->fetch('fi', '00400', 3);
    $this->assertEmpty($response);

    // Empty response is not cached.
    $response = $importer->fetch('fi', '00400', 3);
    $this->assertNotEmpty($response);
  }

}
