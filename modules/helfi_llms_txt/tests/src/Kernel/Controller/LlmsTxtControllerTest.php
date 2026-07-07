<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_llms_txt\Kernel\Controller;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the /llms.txt controller.
 */
#[RunTestsInSeparateProcesses]
#[Group('helfi_llms_txt')]
class LlmsTxtControllerTest extends KernelTestBase {

  use ApiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'helfi_llms_txt',
  ];

  /**
   * Tests that the route serves the configured content as markdown.
   */
  public function testRouteServesContent(): void {
    $content = "# llms.txt\n\nHello, robots.";

    $this->config('helfi_llms_txt.settings')
      ->set('content', $content)
      ->save();

    $request = $this->getMockedRequest('/llms.txt');
    $response = $this->processRequest($request);

    $this->assertEquals(200, $response->getStatusCode());
    $this->assertSame($content, $response->getContent());
  }

}
