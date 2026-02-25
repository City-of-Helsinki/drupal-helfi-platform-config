<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Kernel\Pipeline;

use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_search\EmbeddingsModelInterface;
use Drupal\helfi_search\Pipeline\ContentChunker;
use Drupal\helfi_search\Pipeline\HtmlCleaner;
use Drupal\helfi_search\Pipeline\HtmlExtractor;
use Drupal\helfi_search\Pipeline\MarkdownConverter;
use Drupal\helfi_search\Pipeline\MetadataComposer;
use Drupal\helfi_search\Pipeline\TextNormalizer;
use Drupal\helfi_search\Pipeline\TextPipeline;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * Tests for the TextPipeline.
 *
 * The HTTP client is mocked because there is no web server in kernel tests,
 * and the embeddings model is mocked to avoid real OpenAI API calls.
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
   * Tests the full pipeline produces embeddings for a single entity.
   */
  public function testProcessEntitiesReturnsEmbeddings(): void {
    $pipeline = $this->getSut(
      [new Response(body: '<html><body><h1>Test Article</h1><p>Helsinki</p></body></html>')],
      $this->mockEmbeddingsModel([0.1, 0.2, 0.3])->reveal(),
    );

    $node = $this->createNode([
      'title' => 'Test Article',
      'type' => 'page',
    ]);

    $results = $pipeline->processEntities(['node-1' => $node]);

    $this->assertArrayHasKey('node-1', $results);
    $this->assertCount(1, $results['node-1']);

    $embedding = $results['node-1'][0];
    $this->assertEquals([0.1, 0.2, 0.3], $embedding['vector']);
    $this->assertNotEmpty($embedding['content']);
    $this->assertStringContainsString('Helsinki', $embedding['content']);

    // Empty input returns empty output.
    $results = $pipeline->processEntities([]);
    $this->assertSame([], $results);
  }

  /**
   * Tests that a failing entity is skipped while others succeed.
   */
  public function testSkipsFailedEntityAndProcessesOthers(): void {
    $pipeline = $this->getSut(
      [
        new TransferException('Connection error'),
        new Response(body: '<html><body><p>Content.</p></body></html>'),
      ],
      $this->mockEmbeddingsModel([0.5, 0.6])->reveal(),
    );

    $node1 = $this->createNode([
      'title' => 'Fails',
      'type' => 'page',
    ]);
    $node2 = $this->createNode([
      'title' => 'Works',
      'type' => 'page',
    ]);

    $results = $pipeline->processEntities([
      'fail' => $node1,
      'ok' => $node2,
    ]);

    $this->assertArrayNotHasKey('fail', $results);
    $this->assertArrayHasKey('ok', $results);
  }

  /**
   * Build the TextPipeline with a mocked HTTP client.
   *
   * @param array $responses
   *   Guzzle responses (or exceptions) to queue in the mock HTTP client.
   * @param \Drupal\helfi_search\EmbeddingsModelInterface|null $model
   *   Embeddings model mock. Defaults to a no-op mock.
   */
  private function getSut(array $responses, ?EmbeddingsModelInterface $model = NULL): TextPipeline {
    if (!$model) {
      $model = $this->prophesize(EmbeddingsModelInterface::class)->reveal();
    }

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
      $model,
    );
  }

  /**
   * Create an embeddings model mock that returns the given vector.
   */
  private function mockEmbeddingsModel(array $vector): ObjectProphecy {
    $model = $this->prophesize(EmbeddingsModelInterface::class);
    $model
      ->batchGetEmbedding(Argument::type('array'))
      ->will(function (array $args) use ($vector) {
        return array_map(fn() => $vector, $args[0]);
      });

    return $model;
  }

}
