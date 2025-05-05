<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_org_chart\Kernel;

use Drupal\Core\Cache\MemoryBackend;
use Drupal\helfi_paragraphs_org_chart\OrgChartImporter;
use Drupal\helfi_paragraphs_org_chart\OrgChartStorage;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * Tests org chart storage.
 */
class OrgChartStorageTest extends KernelTestBase {

  use ApiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['helfi_paragraphs_org_chart'];

  /**
   * Tests org chart storage.
   */
  public function testStorage(): void {
    $importer = new OrgChartImporter($this->createMockHttpClient([
      new ClientException('test-error', new Request('GET', '/test'), new Response()),
      new Response(body: file_get_contents(__DIR__ . '/../../fixtures/org-chart.json')),
      new ClientException('another-test-error', new Request('GET', '/test'), new Response()),
    ]));

    $sut = new OrgChartStorage(new MemoryBackend(), $importer);

    // The api returns an error.
    $response = $sut->load('fi', '00400', 3);
    $this->assertEmpty($response);

    // Empty response is not cached.
    $response = $sut->load('fi', '00400', 3);
    $this->assertNotEmpty($response);

    // Non-empty response is not cached (the last exception is not reached.)
    $response = $sut->load('fi', '00400', 3);
    $this->assertNotEmpty($response);

    // Changing parameters changes the cache key.
    $response = $sut->load('sv', '00400', 3);
    $this->assertEmpty($response);
  }

}
