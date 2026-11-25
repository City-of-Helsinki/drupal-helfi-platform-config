<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\EventSubscriber;

use ColinODell\PsrTestLogger\TestLogger;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\helfi_api_base\Azure\PubSub\PubSubMessage;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_platform_config\EventSubscriber\SearchApiReindexSubscriber;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\SearchApiException;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\Tests\helfi_platform_config\Kernel\KernelTestBase;
use Drupal\Tests\helfi_platform_config\Traits\PubSubRecorderTrait;
use Drupal\Tests\helfi_platform_config\Traits\SharedSearchIndexTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests reacting to shared index reindex messages.
 */
#[Group('helfi_platform_config')]
#[RunTestsInSeparateProcesses]
final class SearchApiReindexSubscriberTest extends KernelTestBase {

  use EnvironmentResolverTrait;
  use PubSubRecorderTrait;
  use SharedSearchIndexTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'language',
    'search_api',
    'search_api_test',
    'user',
  ];

  /**
   * Records what the subscriber logs.
   */
  private TestLogger $logger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setUpSearchApi();

    $this->createIndex('embeddings');
    $this->createIndex('news');
    $this->createIndex('suggestions', FALSE);
    $this->createIndexedItems(2);

    $this->logger = new TestLogger();
    $this->container->get('logger.factory')->addLogger($this->logger);
  }

  /**
   * A message from another instance marks every item for reindexing.
   *
   * The frame is received through the PubSub manager, the same way the
   * helfi:azure:pubsub-listen worker receives it.
   */
  public function testMarksItemsForReindexing(): void {
    $this->setActiveProject(Project::ASUMINEN, EnvironmentEnum::Local);

    $this->pubSubManager([
      $this->deliveredFrame(SearchApiReindexSubscriber::message('embeddings', Project::ETUSIVU) + ['timestamp' => 1]),
    ])->receive();

    $this->assertSame(2, $this->remainingItems('embeddings'));
    $this->assertSame(0, $this->remainingItems('news'));
    $this->assertTrue($this->logger->hasRecordThatPasses(
      static fn (array $record): bool => $record['context']['@index'] === 'embeddings' && $record['context']['@sender'] === Project::ETUSIVU,
      RfcLogLevel::INFO,
    ));
  }

  /**
   * The message is handled whichever instance sent it.
   */
  public function testReindexesMessageFromAnyInstance(): void {
    $this->setActiveProject(Project::LIIKENNE, EnvironmentEnum::Local);

    $this->dispatch($this->payload('embeddings', Project::ASUMINEN));

    $this->assertSame(2, $this->remainingItems('embeddings'));
  }

  /**
   * A message is handled when the own instance cannot be resolved.
   */
  public function testReindexesWhenOwnInstanceIsUnknown(): void {
    $this->dispatch($this->payload('embeddings', Project::ETUSIVU));

    $this->assertSame(2, $this->remainingItems('embeddings'));
  }

  /**
   * Messages that must not mark anything for reindexing.
   */
  public function testIgnoresMessagesThatDoNotApply(): void {
    $this->setActiveProject(Project::LIIKENNE, EnvironmentEnum::Local);
    $ignored = [
      'other message type' => ['tags' => ['node:1']],
      'not an array' => ['search_api_reindex' => 'embeddings'],
      'missing index' => ['search_api_reindex' => ['instance' => Project::ETUSIVU]],
      'missing instance' => ['search_api_reindex' => ['index' => 'embeddings']],
      'empty index' => ['search_api_reindex' => ['index' => '', 'instance' => Project::ETUSIVU]],
      'index is not shared' => $this->payload('news', Project::ETUSIVU),
      'shared index is disabled here' => $this->payload('suggestions', Project::ETUSIVU),
      'shared index does not exist here' => $this->payload('hyte', Project::ASUMINEN),
    ];

    foreach ($ignored as $case => $payload) {
      $this->dispatch($payload);

      $this->assertSame(0, $this->remainingItems('embeddings'), $case);
      $this->assertSame(0, $this->remainingItems('news'), $case);
    }
    $this->assertSame([], $this->logger->records);
  }

  /**
   * The sending instance ignores its own message.
   */
  public function testIgnoresOwnMessage(): void {
    $this->setActiveProject(Project::ETUSIVU, EnvironmentEnum::Local);

    $this->dispatch($this->payload('embeddings', Project::ETUSIVU));

    $this->assertSame(0, $this->remainingItems('embeddings'));
    $this->assertSame([], $this->logger->records);
  }

  /**
   * Instances without Search API ignore the message.
   */
  public function testIgnoresMessageWithoutSearchApi(): void {
    $this->setActiveProject(Project::ASUMINEN, EnvironmentEnum::Local);
    $this->disableModules(['search_api_test', 'search_api']);
    $this->container->get('logger.factory')->addLogger($this->logger);

    $this->dispatch($this->payload('embeddings', Project::ETUSIVU));

    $this->assertSame([], $this->logger->records);
  }

  /**
   * A Search API failure is logged and does not stop the PubSub listener.
   *
   * Makes sure a throw from Index::reindex() is caught so it doesn't kill the
   * helfi:azure:pubsub-listen worker.
   */
  public function testLogsReindexFailure(): void {
    $this->setActiveProject(Project::ASUMINEN, EnvironmentEnum::Local);
    $this->container->get('event_dispatcher')->addListener(
      SearchApiEvents::REINDEX_SCHEDULED,
      static fn () => throw new SearchApiException('Tracker failed.'),
    );

    $this->dispatch($this->payload('embeddings', Project::ETUSIVU));

    $this->assertTrue($this->logger->hasRecordThatPasses(
      static fn (array $record): bool => ($record['context']['@message'] ?? NULL) === 'Tracker failed.',
      RfcLogLevel::ERROR,
    ));
  }

  /**
   * Dispatches a received PubSub message through the event dispatcher.
   *
   * @param array<string, mixed> $payload
   *   The message data, as given to sendMessage().
   */
  private function dispatch(array $payload): void {
    $this->container->get('event_dispatcher')->dispatch(new PubSubMessage([
      'type' => 'message',
      'from' => 'group',
      'group' => 'shared',
      'dataType' => 'json',
      'data' => $payload + ['timestamp' => 1],
    ]));
  }

  /**
   * Builds the reindex message data.
   *
   * @return array<string, array<string, string>>
   *   The message data.
   */
  private function payload(string $indexId, string $instance): array {
    return SearchApiReindexSubscriber::message($indexId, $instance);
  }

}
