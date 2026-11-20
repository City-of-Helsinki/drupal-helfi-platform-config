<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\EventSubscriber;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\helfi_api_base\Azure\PubSub\PubSubMessage;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_platform_config\EventSubscriber\SearchApiReindexSubscriber;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\SearchApiException;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * Tests reacting to shared index reindex messages.
 */
#[Group('helfi_platform_config')]
final class SearchApiReindexSubscriberTest extends UnitTestCase {

  use EnvironmentResolverTrait;

  /**
   * The logger.
   */
  private LoggerInterface&MockObject $logger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->logger = $this->createMock(LoggerInterface::class);
  }

  /**
   * Tests the subscribed events.
   */
  public function testGetSubscribedEvents(): void {
    $this->assertSame([
      PubSubMessage::class => ['onReceive'],
    ], SearchApiReindexSubscriber::getSubscribedEvents());
  }

  /**
   * A message from another instance marks the index for reindexing.
   */
  public function testReindexesForOtherInstance(): void {
    $index = $this->index(TRUE);
    $index->expects($this->once())->method('reindex');
    $this->logger->expects($this->once())->method('info');

    $this->subscriber(Project::ASUMINEN, $index)
      ->onReceive($this->message('embeddings', Project::ETUSIVU));
  }

  /**
   * A message is still handled when the own instance cannot be resolved.
   */
  public function testReindexesWithUnknownOwnInstance(): void {
    $index = $this->index(TRUE);
    $index->expects($this->once())->method('reindex');

    $this->subscriber(NULL, $index)
      ->onReceive($this->message('embeddings', Project::ETUSIVU));
  }

  /**
   * Messages that must not trigger a reindex.
   *
   * @param array<string, mixed> $data
   *   The received PubSub message data.
   */
  #[DataProvider('ignoredMessages')]
  public function testIgnoredMessages(array $data): void {
    $index = $this->index(TRUE);
    $index->expects($this->never())->method('reindex');

    $this->subscriber(Project::ASUMINEN, $index)->onReceive(new PubSubMessage($data));
  }

  /**
   * Data provider for testIgnoredMessages().
   *
   * @return array<string, array{array<string, mixed>}>
   *   The test cases.
   */
  public static function ignoredMessages(): array {
    return [
      'empty' => [[]],
      'cache tags' => [['data' => ['tags' => ['node:1']]]],
      'not an array' => [['data' => ['search_api_reindex' => 'embeddings']]],
      'missing index' => [['data' => ['search_api_reindex' => ['instance' => Project::ETUSIVU]]]],
      'missing instance' => [['data' => ['search_api_reindex' => ['index' => 'embeddings']]]],
      'empty index' => [['data' => ['search_api_reindex' => ['index' => '', 'instance' => Project::ETUSIVU]]]],
      'own message' => [
        ['data' => ['search_api_reindex' => ['index' => 'embeddings', 'instance' => Project::ASUMINEN]]],
      ],
      'not shared' => [['data' => ['search_api_reindex' => ['index' => 'news', 'instance' => Project::ETUSIVU]]]],
    ];
  }

  /**
   * Missing and disabled indexes are skipped.
   */
  public function testSkipsMissingAndDisabledIndex(): void {
    $this->subscriber(Project::ASUMINEN, NULL)
      ->onReceive($this->message('embeddings', Project::ETUSIVU));

    $disabled = $this->index(FALSE);
    $disabled->expects($this->never())->method('reindex');
    $this->subscriber(Project::ASUMINEN, $disabled)
      ->onReceive($this->message('embeddings', Project::ETUSIVU));
  }

  /**
   * Nothing is loaded when Search API is not installed.
   */
  public function testSkipsWithoutSearchApi(): void {
    $index = $this->index(TRUE);
    $index->expects($this->never())->method('reindex');

    $this->subscriber(Project::ASUMINEN, $index, searchApi: FALSE)
      ->onReceive($this->message('embeddings', Project::ETUSIVU));
  }

  /**
   * A Search API failure is logged and does not escape.
   */
  public function testLogsReindexFailure(): void {
    $index = $this->index(TRUE);
    $index->method('reindex')->willThrowException(new SearchApiException('Tracker failed.'));
    $this->logger->expects($this->once())->method('log');

    $this->subscriber(Project::ASUMINEN, $index)
      ->onReceive($this->message('embeddings', Project::ETUSIVU));
  }

  /**
   * Builds the subscriber.
   *
   * @param string|null $project
   *   The own project name, or NULL for an unresolved project.
   * @param \Drupal\search_api\IndexInterface|null $index
   *   The index returned by the storage.
   * @param bool $searchApi
   *   Whether the search_api_index entity type exists.
   */
  private function subscriber(?string $project, ?IndexInterface $index, bool $searchApi = TRUE): SearchApiReindexSubscriber {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($index);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('hasDefinition')->with('search_api_index')->willReturn($searchApi);
    $entityTypeManager->method('getStorage')->with('search_api_index')->willReturn($storage);

    return new SearchApiReindexSubscriber(
      $project === NULL
        ? $this->getEnvironmentResolver()
        : $this->getEnvironmentResolver($project, EnvironmentEnum::Local),
      $entityTypeManager,
      $this->logger,
    );
  }

  /**
   * Builds a received reindex message.
   */
  private function message(string $indexId, string $instance): PubSubMessage {
    return new PubSubMessage([
      'data' => [
        'search_api_reindex' => ['index' => $indexId, 'instance' => $instance],
        'timestamp' => 1,
      ],
    ]);
  }

  /**
   * Builds an index with the given enabled state.
   */
  private function index(bool $enabled): IndexInterface&MockObject {
    $index = $this->createMock(IndexInterface::class);
    $index->method('status')->willReturn($enabled);
    return $index;
  }

}
