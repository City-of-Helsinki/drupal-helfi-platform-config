<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Kernel\Queue;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity_test\Entity\EntityTestMulRevPub;
use Drupal\helfi_search\DocumentState;
use Drupal\helfi_search\EmbeddingApiInterface;
use Drupal\helfi_search\EmbeddingModel;
use Drupal\helfi_search\Pipeline\Chunk;
use Drupal\helfi_search\Pipeline\PipelineException;
use Drupal\helfi_search\Pipeline\TextPipeline;
use Drupal\helfi_search\Queue\DTO\ClaimedDocument;
use Drupal\helfi_search\Queue\QueueException;
use Drupal\helfi_search\Queue\QueueManager;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager;
use Drupal\Tests\helfi_platform_config\Kernel\KernelTestBase;
use Drupal\Tests\helfi_search\Traits\EmbeddingStoreTrait;
use Drupal\Tests\helfi_search\Traits\TestClockTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * Tests queue processing.
 */
#[Group('helfi_search')]
#[RunTestsInSeparateProcesses]
class QueueManagerProcessTest extends KernelTestBase {

  use ProphecyTrait;
  use EmbeddingStoreTrait;
  use TestClockTrait;

  /**
   * The entity type used as the document.
   */
  private const string ENTITY_TYPE = 'entity_test_mulrevpub';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'language',
    'entity_test',
    'search_api',
    'helfi_search',
  ];

  /**
   * The text pipeline.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy<\Drupal\helfi_search\Pipeline\TextPipeline>
   */
  private ObjectProphecy $textPipeline;

  /**
   * The embeddings API.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy<\Drupal\helfi_search\EmbeddingApiInterface>
   */
  private ObjectProphecy $embeddingsApi;

  /**
   * The search api tracking manager.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy<\Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager>
   */
  private ObjectProphecy $trackingManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema(self::ENTITY_TYPE);
    $this->installSchema('helfi_search', [
      'helfi_search_document',
      'helfi_search_chunk',
    ]);

    ConfigurableLanguage::create(['id' => 'sv'])->save();

    $this->textPipeline = $this->prophesize(TextPipeline::class);
    $this->embeddingsApi = $this->prophesize(EmbeddingApiInterface::class);
    $this->trackingManager = $this->prophesize(ContentEntityTrackingManager::class);

    $this->startClock();
  }

  /**
   * Tests the pipeline with an entity that was deleted before the pipeline ran.
   */
  public function testProcessRemovesDeletedEntity(): void {
    $key = [
      'entity_type' => self::ENTITY_TYPE,
      'entity_id' => '404',
      'langcode' => 'en',
    ];
    $this->setDocumentState($key, DocumentState::Embedding);
    $this->fillChunks($key, [
      new Chunk(text: 'First'),
      new Chunk(text: 'Second'),
    ]);

    $this->getSut()->process(new ClaimedDocument(
      entityType: self::ENTITY_TYPE,
      entityId: '404',
      langcode: 'en',
      changed: $this->now,
    ));

    $this->textPipeline->process(Argument::any())->shouldNotHaveBeenCalled();

    // The row is dropped instead of being re-claimed over and over again.
    $this->assertSame([], $this->countByState());

    // Chunks are removed.
    $this->assertSame(0, $this->countChunks());
  }

  /**
   * Tests that the embed vectors are created.
   */
  public function testProcessCreatesEmbedVectors(): void {
    $entity = $this->createEntity();
    $translation = $entity->addTranslation('sv', ['name' => 'Svenska']);
    $translation->save();

    $this->setDocumentState($translation, DocumentState::Embedding);

    $chunks = [
      new Chunk(text: 'Body text', snippet: 'Body', fragment: 'how-to-apply'),
      new Chunk(text: 'More body text', snippet: str_repeat('a ', 200), fragment: 'requirements'),
    ];

    $this->textPipeline
      ->process(Argument::that(static fn ($entity) => $entity->language()->getId() === 'sv'))
      ->willReturn($chunks)
      ->shouldBeCalledOnce();

    $this->expectEmbedding($chunks);
    $this->expectTracking($entity, 'sv');

    $this->getSut()->process(new ClaimedDocument(
      entityType: $entity->getEntityTypeId(),
      entityId: (string) $entity->id(),
      langcode: 'sv',
      changed: $this->now,
    ));

    $this->assertDocumentState($translation, DocumentState::Ready);
    $this->assertSame(['sv', 'sv'], $this->chunkColumn('langcode'));

    $storedChunks = $this->readChunks($translation);
    $this->assertCount(2, $storedChunks);

    foreach ($chunks as $i => $chunk) {
      $this->assertEqualsWithDelta([0.25, 0.5], $storedChunks[$i]->vector, 1e-6);
      $this->assertSame($chunk->getTruncatedSnippet(), $storedChunks[$i]->snippet);
      $this->assertSame($chunk->fragment, $storedChunks[$i]->fragment);
    }

    $this->assertSame(
      array_map(static fn (Chunk $chunk) => $chunk->contentHash(), $chunks),
      $this->chunkColumn('content_hash'),
    );
  }

  /**
   * Tests that unpublished documents produce no embeddings.
   */
  public function testProcessSkipsUnpublishedEntity(): void {
    $entity = $this->createEntity(published: FALSE);
    $this->setDocumentState($entity, DocumentState::Embedding);
    $this->fillChunks($entity, [
      new Chunk(text: 'First'),
      new Chunk(text: 'Second'),
    ]);

    $this->advanceTime(10);
    $this->getSut()->process(new ClaimedDocument(
      entityType: $entity->getEntityTypeId(),
      entityId: (string) $entity->id(),
      langcode: 'en',
      changed: $this->now,
    ));

    $this->assertDocumentState($entity, DocumentState::Skipped, changed: $this->now);
    $this->textPipeline->process(Argument::any())->shouldNotHaveBeenCalled();
  }

  /**
   * Tests that unchanged documents are neither re-embedded nor re-indexed.
   */
  public function testProcessReusesStoredVectorsWhenNothingChanged(): void {
    $entity = $this->createEntity();
    $this->setDocumentState($entity, DocumentState::Embedding);

    $chunks = [
      new Chunk(text: 'Body text', snippet: 'Body', fragment: 'how-to-apply'),
      new Chunk(text: 'More body text', snippet: 'More', fragment: 'requirements'),
    ];
    $this->fillChunks($entity, $chunks);

    $this->textPipeline->process(Argument::any())->willReturn($chunks);
    $this->embeddingsApi->batchGetEmbedding(Argument::cetera())->shouldNotBeCalled();
    $this->trackingManager->getIndexesForEntity(Argument::any())->shouldNotBeCalled();

    $this->advanceTime(10);
    $this->getSut()->process(new ClaimedDocument(
      entityType: $entity->getEntityTypeId(),
      entityId: (string) $entity->id(),
      langcode: 'en',
      changed: $this->now,
    ));

    $this->assertDocumentState($entity, DocumentState::Ready);
  }

  /**
   * Tests that chunks dropped by the pipeline are removed.
   */
  public function testProcessRemovesStaleChunks(): void {
    $entity = $this->createEntity();
    $this->setDocumentState($entity, DocumentState::Embedding);

    $chunks = [
      new Chunk(text: 'First', snippet: 'First'),
      new Chunk(text: 'Second', snippet: 'Second'),
      new Chunk(text: 'Third', snippet: 'Third'),
    ];
    $this->fillChunks($entity, $chunks);

    // The pipeline does not return the third chunk.
    $this->textPipeline->process(Argument::any())
      ->willReturn([$chunks[0], $chunks[1]]);
    // The content is identical, so no need to re-generate the vectors.
    $this->embeddingsApi->batchGetEmbedding(Argument::cetera())->shouldNotBeCalled();
    // But we expect reindexing to elasticsearch.
    $this->expectTracking($entity);

    $this->getSut()->process(new ClaimedDocument(
      entityType: $entity->getEntityTypeId(),
      entityId: (string) $entity->id(),
      langcode: 'en',
      changed: $this->now,
    ));

    $this->assertSame(2, $this->countChunks());
    $this->assertSame(
      array_map(static fn (Chunk $chunk) => $chunk->contentHash(), [$chunks[0], $chunks[1]]),
      $this->chunkColumn('content_hash'),
    );
  }

  /**
   * Tests that a failed pipeline run is counted and re-queued.
   */
  public function testProcessRecordsFailure(): void {
    $entity = $this->createEntity();
    $this->setDocumentState($entity, DocumentState::Pending, attempts: 0);

    $previous = new PipelineException('boom');
    $this->textPipeline->process(Argument::any())->willThrow($previous);

    for ($i = 0; $i < QueueManager::MAX_ATTEMPTS; $i++) {
      $this->assertDocumentState($entity, DocumentState::Pending, attempts: $i, changed: $this->now);
      $this->advanceTime(10);

      try {
        $this->getSut()->process(new ClaimedDocument(
          entityType: $entity->getEntityTypeId(),
          entityId: (string) $entity->id(),
          langcode: 'en',
          changed: $this->now,
        ));
        $this->fail('Expected a QueueException.');
      }
      catch (QueueException $e) {
        $this->assertSame('boom', $e->getMessage());
        $this->assertSame($previous, $e->getPrevious());
      }
    }

    // Document gives up after the maximum number of attempts.
    $this->assertDocumentState($entity, DocumentState::Failed, attempts: QueueManager::MAX_ATTEMPTS, changed: $this->now);
  }

  /**
   * Builds the queue manager.
   */
  private function getSut(): QueueManager {
    return new QueueManager(
      $this->container->get(Connection::class),
      $this->time(),
      $this->trackingManager->reveal(),
      $this->textPipeline->reveal(),
      $this->embeddingsApi->reveal(),
      $this->container->get(EntityTypeManagerInterface::class),
    );
  }

  /**
   * Creates a document entity.
   */
  private function createEntity(bool $published = TRUE): ContentEntityInterface {
    $entity = EntityTestMulRevPub::create([
      'name' => 'Test',
      'type' => self::ENTITY_TYPE,
      'langcode' => 'en',
      'status' => $published,
    ]);
    $entity->save();

    return $entity;
  }

  /**
   * Expects the given chunks to be embedded, returning a fixed vector.
   *
   * @param \Drupal\helfi_search\Pipeline\Chunk[] $chunks
   *   The chunks that have no stored vector yet.
   */
  private function expectEmbedding(array $chunks): void {
    $expected = [];
    foreach ($chunks as $chunk) {
      $expected[$chunk->contentHash()] = (string) $chunk;
    }

    $this->embeddingsApi
      ->batchGetEmbedding($expected, EmbeddingModel::DEFAULT)
      ->willReturn(array_fill_keys(array_keys($expected), [0.25, 0.5]))
      ->shouldBeCalledOnce();
  }

  /**
   * Expects the entity translation to be marked for re-indexing.
   */
  private function expectTracking(ContentEntityInterface $entity, string $langcode = 'en'): void {
    $index = $this->stubIndex();
    $index->trackItemsUpdated(
      'entity:' . self::ENTITY_TYPE,
      [$entity->id() . ':' . $langcode],
    )->shouldBeCalledOnce();

    $this->trackingManager->getIndexesForEntity(Argument::any())
      ->willReturn(['embeddings' => $index->reveal()]);
  }

  /**
   * Builds a search api index.
   *
   * An index with no valid datasource passes item IDs through untouched, which
   * keeps the static filtering in ContentEntityTrackingManager out of the way.
   *
   * @return \Prophecy\Prophecy\ObjectProphecy<\Drupal\search_api\IndexInterface>
   *   The index.
   */
  private function stubIndex(bool $hasProcessor = TRUE): ObjectProphecy {
    $index = $this->prophesize(IndexInterface::class);
    $index->isValidProcessor(QueueManager::PROCESSOR_ID)->willReturn($hasProcessor);
    $index->isValidDatasource(Argument::any())->willReturn(FALSE);

    return $index;
  }

}
