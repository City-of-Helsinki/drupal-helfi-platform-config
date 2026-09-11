<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Kernel\Queue;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\helfi_search\DocumentState;
use Drupal\helfi_search\EmbeddingApiInterface;
use Drupal\helfi_search\Pipeline\TextPipeline;
use Drupal\helfi_search\Queue\QueueManager;
use Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager;
use Drupal\Tests\helfi_platform_config\Kernel\KernelTestBase;
use Drupal\Tests\helfi_search\Traits\EmbeddingStoreTrait;
use Drupal\Tests\helfi_search\Traits\TestClockTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests the work list side of the queue manager.
 *
 * @see \Drupal\Tests\helfi_search\Kernel\Queue\QueueManagerProcessTest
 */
#[CoversClass(QueueManager::class)]
#[Group('helfi_search')]
#[RunTestsInSeparateProcesses]
class QueueManagerClaimTest extends KernelTestBase {

  use ProphecyTrait;
  use EmbeddingStoreTrait;
  use TestClockTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'entity_test',
    'search_api',
    'helfi_search',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test');
    $this->installSchema('helfi_search', [
      'helfi_search_document',
      'helfi_search_chunk',
    ]);

    $this->startClock();
  }

  /**
   * Tests claiming documents.
   */
  public function testClaimPendingDocuments(): void {
    $entity = $this->createEntity();
    $this->setDocumentState($entity, DocumentState::Pending, attempts: 0);
    $this->advanceTime(60);

    $claimed = $this->getSut()->claimPending();

    $this->assertCount(1, $claimed);
    $this->assertSame(self::key($entity), array_first($claimed)->key());
    $this->assertSame($this->now, array_first($claimed)->changed);

    // The claimed document should be immediately pending.
    $this->assertTrue($this->getSut()->isPending(array_first($claimed)));

    $this->assertDocumentState($entity, DocumentState::Embedding, attempts: 0, changed: $this->now);

    // There should be no more documents to claim.
    $claimed = $this->getSut()->claimPending();
    $this->assertCount(0, $claimed);
  }

  /**
   * Tests that settled documents are not claimed.
   */
  public function testClaimIgnoreRules(): void {
    // Tests claiming from no rows.
    $this->assertSame([], $this->getSut()->claimPending());

    $states = array_filter(
      DocumentState::cases(),
      static fn (DocumentState $enum) => !in_array(
        $enum,
        [DocumentState::Pending, DocumentState::Embedding],
        TRUE,
      ),
    );

    foreach ($states as $state) {
      $entity = $this->createEntity();
      $this->setDocumentState(
        $entity,
        $state,
        changed: $this->now - 10 * QueueManager::STALE_WINDOW,
      );
    }

    $this->assertSame([], $this->getSut()->claimPending());
  }

  /**
   * Tests the exponential back-off between retries.
   */
  #[DataProvider('backOffProvider')]
  public function testClaimPendingAppliesBackOff(int $attempts, int $elapsed, bool $expected): void {
    $entity = $this->createEntity();
    $this->setDocumentState($entity, DocumentState::Pending, attempts: $attempts);
    $this->advanceTime($elapsed);

    $claimed = $this->getSut()->claimPending();

    $this->assertCount($expected ? 1 : 0, $claimed);
    $this->assertDocumentState(
      $entity,
      $expected ? DocumentState::Embedding : DocumentState::Pending,
    );
  }

  /**
   * Data provider for testClaimPendingAppliesBackOff.
   *
   * @return array<string, array{int, int, bool}>
   *   Attempt count, seconds waited and whether the document is claimed.
   */
  public static function backOffProvider(): array {
    return [
      'first run' => [0, 0, TRUE],
      'one attempt, too soon' => [1, QueueManager::RETRY_DELAY - 5, FALSE],
      'one attempt, elapsed' => [1, QueueManager::RETRY_DELAY, TRUE],
      'two attempts, elapsed' => [2, QueueManager::RETRY_DELAY * 2, TRUE],
    ];
  }

  /**
   * Tests that documents stuck in `embedding` are eventually retried.
   */
  #[DataProvider('staleProvider')]
  public function testClaimPendingReclaimsStaleEmbedding(int $elapsed, bool $expected): void {
    $entity = $this->createEntity();
    $this->setDocumentState($entity, DocumentState::Embedding);
    $this->advanceTime($elapsed);

    $claimed = $this->getSut()->claimPending();

    $this->assertCount($expected ? 1 : 0, $claimed);
    $this->assertDocumentState(
      $entity,
      DocumentState::Embedding,
      changed: $expected ? $this->now : $this->now - $elapsed,
    );

    // Nothing more to claim.
    $this->assertEmpty($this->getSut()->claimPending());
  }

  /**
   * Data provider for testClaimPendingReclaimsStaleEmbedding.
   *
   * @return array<string, array{int, bool}>
   *   Seconds spent in `embedding` and whether the document is reclaimed.
   */
  public static function staleProvider(): array {
    return [
      'before the window' => [QueueManager::STALE_WINDOW - 1, FALSE],
      'past the window' => [QueueManager::STALE_WINDOW + 1, TRUE],
    ];
  }

  /**
   * Builds the queue manager.
   */
  private function getSut(): QueueManager {
    return new QueueManager(
      $this->container->get(Connection::class),
      $this->time(),
      $this->prophesize(ContentEntityTrackingManager::class)->reveal(),
      $this->prophesize(TextPipeline::class)->reveal(),
      $this->prophesize(EmbeddingApiInterface::class)->reveal(),
      $this->container->get(EntityTypeManagerInterface::class),
    );
  }

  /**
   * Creates a document entity.
   */
  private function createEntity(): ContentEntityInterface {
    $entity = EntityTest::create([
      'name' => 'Test',
      'type' => 'entity_test',
      'langcode' => 'en',
    ]);
    $entity->save();

    return $entity;
  }

}
