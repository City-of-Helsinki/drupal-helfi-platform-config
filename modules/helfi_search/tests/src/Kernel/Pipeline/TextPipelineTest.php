<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Kernel\Pipeline;

use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_search\Pipeline\ContentChunker;
use Drupal\helfi_search\Pipeline\HtmlCleaner;
use Drupal\helfi_search\Pipeline\HtmlExtractor;
use Drupal\helfi_search\Pipeline\MarkdownConverter;
use Drupal\helfi_search\Pipeline\MetadataComposer;
use Drupal\helfi_search\Pipeline\TextNormalizer;
use Drupal\helfi_search\Pipeline\PipelineException;
use Drupal\helfi_search\Pipeline\TextPipeline;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests for the TextPipeline.
 *
 * The HTTP client is mocked because there is no web server in kernel tests.
 */
#[Group('helfi_search')]
#[RunTestsInSeparateProcesses]
class TextPipelineTest extends KernelTestBase {

  use ProphecyTrait;
  use NodeCreationTrait;
  use ApiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'filter',
    'path_alias',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('path_alias');

    NodeType::create(['type' => 'page'])->save();
  }

  /**
   * Tests extractChunks returns chunks for a single entity.
   */
  public function testExtractChunksReturnsChunks(): void {
    $pipeline = $this->getSut(
      [new Response(body: '<html><body><h1>Test Article</h1><p>Helsinki</p></body></html>')],
    );

    $node = $this->createNode([
      'title' => 'Test Article',
      'type' => 'page',
    ]);

    $result = $pipeline->extractChunks(['node-1' => $node]);

    $this->assertArrayHasKey('node-1', $result);
    $this->assertCount(1, $result['node-1']);
    $this->assertStringContainsString('Helsinki', $result['node-1'][0]);

    // Empty input returns empty output.
    $result = $pipeline->extractChunks([]);
    $this->assertEmpty($result);
  }

  /**
   * Tests that a failing entity causes the whole pipeline to fail.
   */
  public function testFailingEntityFailsPipeline(): void {
    $pipeline = $this->getSut(
      [new TransferException('Connection error')],
    );

    $node = $this->createNode([
      'title' => 'Fails',
      'type' => 'page',
    ]);

    $this->expectException(PipelineException::class);
    $this->expectExceptionMessage('Connection error');
    $pipeline->extractChunks(['fail' => $node]);
  }

  /**
   * Build the TextPipeline with a mocked HTTP client.
   *
   * @param array $responses
   *   Guzzle responses (or exceptions) to queue in the mock HTTP client.
   */
  private function getSut(array $responses): TextPipeline {
    $environmentResolver = $this->prophesize(EnvironmentResolverInterface::class);
    $environmentResolver->getActiveEnvironmentName()->willReturn('testing');

    $htmlExtractor = new HtmlExtractor(
      $this->createMockHttpClient($responses),
      $environmentResolver->reveal(),
    );

    return new TextPipeline(
      $htmlExtractor,
      new HtmlCleaner(),
      new MarkdownConverter(),
      new TextNormalizer(),
      new ContentChunker(),
      new MetadataComposer(),
    );
  }

}
