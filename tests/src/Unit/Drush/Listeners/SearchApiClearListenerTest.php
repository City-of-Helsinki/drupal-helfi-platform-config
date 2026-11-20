<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\Drush\Listeners;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\helfi_api_base\Azure\PubSub\PubSubManagerInterface;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_platform_config\Drush\Listeners\SearchApiClearListener;
use Drupal\helfi_platform_config\SearchAPI\RestrictedSearchIndex;
use Drupal\search_api\IndexInterface;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use WebSocket\Exception\ClientException;

/**
 * Unit tests for the sapi-c command listener.
 */
#[Group('helfi_platform_config')]
final class SearchApiClearListenerTest extends UnitTestCase {

  use EnvironmentResolverTrait;

  /**
   * A named index outside the restricted list is cleared without a prompt.
   */
  public function testAllowsUnrestrictedIndex(): void {
    $listener = $this->listener();
    $listener->entityTypeManager->expects($this->never())->method('getStorage');
    $listener->logger->expects($this->never())->method('error');
    $event = $this->event('news');

    $listener->onSearchApiClear($event);

    $this->assertTrue($event->commandShouldRun());
    $this->assertSame(0, $listener->confirmCalls);
  }

  /**
   * Other commands are left alone.
   */
  public function testIgnoresOtherCommands(): void {
    $listener = $this->listener();
    $listener->logger->expects($this->never())->method('error');
    $event = $this->event('embeddings', 'cache:rebuild');

    $listener->onSearchApiClear($event);

    $this->assertTrue($event->commandShouldRun());
    $this->assertSame(0, $listener->confirmCalls);
  }

  /**
   * On instances other than etusivu, clearing shared indexes is rejected.
   */
  public function testBlocksRestrictedIndexOffEtusivu(): void {
    $listener = $this->listener();
    $listener->logger->expects($this->once())
      ->method('error')
      ->with("Cannot clear the following shared indexes here:\n- embeddings, clear on etusivu");
    $event = $this->event('embeddings');

    $listener->onSearchApiClear($event);

    $this->assertFalse($event->commandShouldRun());
    $this->assertSame(0, $listener->confirmCalls);
  }

  /**
   * On etusivu, a confirmed clear of a shared index proceeds.
   */
  public function testConfirmsRestrictedIndexOnEtusivu(): void {
    $listener = $this->listener(Project::ETUSIVU);
    $listener->confirmResult = TRUE;
    $listener->logger->expects($this->never())->method('error');
    $event = $this->event('suggestions');

    $listener->onSearchApiClear($event);

    $this->assertTrue($event->commandShouldRun());
    $this->assertSame(1, $listener->confirmCalls);
    $this->assertSame('The search index suggestions is in shared use. Clear it anyway?', $listener->confirmQuestion);
  }

  /**
   * On etusivu, declining the confirmation disables the clear.
   */
  public function testAbortsWhenConfirmationIsDeclined(): void {
    $listener = $this->listener(Project::ETUSIVU);
    $listener->confirmResult = FALSE;
    $question = 'The search index embeddings is in shared use. Clear it anyway?';
    $listener->logger->expects($this->once())->method('error')->with($question);
    $event = $this->event('embeddings');

    $listener->onSearchApiClear($event);

    $this->assertFalse($event->commandShouldRun());
    $this->assertSame(1, $listener->confirmCalls);
  }

  /**
   * Clearing every index aborts when an enabled restricted index is included.
   */
  public function testBlocksClearAllWhenRestrictedIndexIsEnabled(): void {
    $listener = $this->listener(
      indexes: [
        $this->index('news', TRUE),
        $this->index('embeddings', TRUE),
        $this->index('suggestions', TRUE),
      ],
    );
    $listener->logger->expects($this->once())
      ->method('error')
      ->with("Cannot clear the following shared indexes here:\n- embeddings, clear on etusivu\n- suggestions, clear on etusivu");
    $event = $this->event(NULL);

    $listener->onSearchApiClear($event);

    $this->assertFalse($event->commandShouldRun());
  }

  /**
   * A disabled restricted index is not part of a clear with no id.
   */
  public function testAllowsClearAllWhenRestrictedIndexIsDisabled(): void {
    $listener = $this->listener(
      indexes: [
        $this->index('news', TRUE),
        $this->index('embeddings', FALSE),
      ],
    );
    $listener->logger->expects($this->never())->method('error');
    $event = $this->event(NULL);

    $listener->onSearchApiClear($event);

    $this->assertTrue($event->commandShouldRun());
    $this->assertSame(0, $listener->confirmCalls);
  }

  /**
   * On etusivu, a clear of every index asks once and names each shared index.
   */
  public function testConfirmsClearAllOnEtusivu(): void {
    $listener = $this->listener(
      Project::ETUSIVU,
      indexes: [
        $this->index('suggestions', TRUE),
        $this->index('news', TRUE),
        $this->index('embeddings', TRUE),
      ],
    );
    $listener->confirmResult = TRUE;
    $listener->logger->expects($this->never())->method('error');
    $event = $this->event(NULL);

    $listener->onSearchApiClear($event);

    $this->assertTrue($event->commandShouldRun());
    $this->assertSame(1, $listener->confirmCalls);
    $this->assertSame('The search indexes suggestions, embeddings are in shared use. Clear them anyway?', $listener->confirmQuestion);
  }

  /**
   * Hyte is blocked on etusivu and confirmed on asuminen.
   */
  public function testHyteFollowsAsuminen(): void {
    $etusivu = $this->listener(Project::ETUSIVU);
    $etusivu->logger->expects($this->once())
      ->method('error')
      ->with("Cannot clear the following shared indexes here:\n- hyte, clear on asuminen");
    $blocked = $this->event('hyte');
    $etusivu->onSearchApiClear($blocked);
    $this->assertFalse($blocked->commandShouldRun());

    $asuminen = $this->listener(Project::ASUMINEN);
    $asuminen->confirmResult = TRUE;
    $asuminen->logger->expects($this->never())->method('error');
    $confirmed = $this->event('hyte');
    $asuminen->onSearchApiClear($confirmed);
    $this->assertTrue($confirmed->commandShouldRun());
    $this->assertSame('The search index hyte is in shared use. Clear it anyway?', $asuminen->confirmQuestion);
  }

  /**
   * A clear of every index stops when any included index is blocked.
   */
  public function testBlocksClearAllWhenMainsDiffer(): void {
    $listener = $this->listener(
      Project::LIIKENNE,
      indexes: [
        $this->index('embeddings', TRUE),
        $this->index('hyte', TRUE),
      ],
    );
    $listener->logger->expects($this->once())
      ->method('error')
      ->with("Cannot clear the following shared indexes here:\n- embeddings, clear on etusivu\n- hyte, clear on asuminen");
    $event = $this->event(NULL);

    $listener->onSearchApiClear($event);

    $this->assertFalse($event->commandShouldRun());
    $this->assertSame(0, $listener->confirmCalls);
  }

  /**
   * A successful clear of a shared index notifies other instances.
   */
  public function testTerminateSendsReindexMessage(): void {
    $listener = $this->listener(Project::ETUSIVU, [$this->index('embeddings', TRUE)]);
    $listener->pubSubManager->expects($this->once())
      ->method('sendMessage')
      ->with(['search_api_reindex' => ['index' => 'embeddings', 'instance' => Project::ETUSIVU]]);
    $listener->logger->expects($this->once())
      ->method('notice')
      ->with('Asked other instances to reindex the shared index embeddings.');

    $listener->onSearchApiClearTerminate($this->terminateEvent('embeddings'));
  }

  /**
   * Nothing is sent when the clear did not succeed or was another command.
   */
  public function testTerminateIgnoresFailedClearAndOtherCommands(): void {
    $listener = $this->listener(Project::ETUSIVU, [$this->index('embeddings', TRUE)]);
    $listener->pubSubManager->expects($this->never())->method('sendMessage');

    $listener->onSearchApiClearTerminate($this->terminateEvent('embeddings', exitCode: ConsoleCommandEvent::RETURN_CODE_DISABLED));
    $listener->onSearchApiClearTerminate($this->terminateEvent('embeddings', exitCode: 1));
    $listener->onSearchApiClearTerminate($this->terminateEvent('embeddings', 'search-api:index'));
  }

  /**
   * Nothing is sent for indexes that are not shared, unknown or disabled.
   */
  public function testTerminateIgnoresIndexesThatWereNotClearedShared(): void {
    $listener = $this->listener(Project::ETUSIVU, [
      $this->index('news', TRUE),
      $this->index('suggestions', FALSE),
    ]);
    $listener->pubSubManager->expects($this->never())->method('sendMessage');

    $listener->onSearchApiClearTerminate($this->terminateEvent('news'));
    $listener->onSearchApiClearTerminate($this->terminateEvent('suggestions'));
    $listener->onSearchApiClearTerminate($this->terminateEvent('hyte'));
  }

  /**
   * A clear of every index sends one message per enabled shared index.
   */
  public function testTerminateClearAllSendsPerSharedIndex(): void {
    $listener = $this->listener(Project::ASUMINEN, [
      $this->index('news', TRUE),
      $this->index('embeddings', TRUE),
      $this->index('suggestions', FALSE),
      $this->index('hyte', TRUE),
    ]);
    $sent = [];
    $listener->pubSubManager->expects($this->exactly(2))
      ->method('sendMessage')
      ->willReturnCallback(function (array $message) use (&$sent, $listener) {
        $sent[] = $message['search_api_reindex'];
        return $listener->pubSubManager;
      });

    $listener->onSearchApiClearTerminate($this->terminateEvent(NULL));

    $this->assertSame([
      ['index' => 'embeddings', 'instance' => Project::ASUMINEN],
      ['index' => 'hyte', 'instance' => Project::ASUMINEN],
    ], $sent);
  }

  /**
   * Nothing is sent when the sending instance is unknown.
   */
  public function testTerminateWithUnknownInstance(): void {
    $listener = $this->listener(NULL, [$this->index('embeddings', TRUE)]);
    $listener->pubSubManager->expects($this->never())->method('sendMessage');
    $listener->logger->expects($this->once())
      ->method('warning')
      ->with('Other instances were not asked to reindex, because the active project is unknown.');

    $listener->onSearchApiClearTerminate($this->terminateEvent('embeddings'));
  }

  /**
   * A PubSub failure is logged and does not fail the command.
   */
  public function testTerminateLogsPubSubFailure(): void {
    $listener = $this->listener(Project::ETUSIVU, [$this->index('embeddings', TRUE)]);
    $listener->pubSubManager->method('sendMessage')
      ->willThrowException(new ClientException('PubSub access key is undefined.'));
    $listener->logger->expects($this->once())
      ->method('warning')
      ->with('Other instances were not asked to reindex the shared index embeddings: PubSub access key is undefined.');

    $listener->onSearchApiClearTerminate($this->terminateEvent('embeddings'));
  }

  /**
   * Builds the listener with a stand-in for the confirmation prompt.
   *
   * @param string|null $project
   *   The active project name, or NULL for an unresolved project.
   * @param \Drupal\search_api\IndexInterface[] $indexes
   *   The indexes that exist.
   */
  private function listener(?string $project = Project::LIIKENNE, array $indexes = []): SearchApiClearListenerUnderTest {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadMultiple')->willReturnCallback(
      static fn (?array $ids = NULL): array => array_values(array_filter(
        $indexes,
        static fn (IndexInterface $index): bool => $ids === NULL || in_array($index->id(), $ids, TRUE),
      )),
    );

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('search_api_index')->willReturn($storage);

    $environmentResolver = $project === NULL
      ? $this->getEnvironmentResolver()
      : $this->getEnvironmentResolver($project, EnvironmentEnum::Local);

    return new SearchApiClearListenerUnderTest(
      new RestrictedSearchIndex($environmentResolver),
      $entityTypeManager,
      $this->createMock(LoggerInterface::class),
      $this->createMock(PubSubManagerInterface::class),
      $environmentResolver,
    );
  }

  /**
   * Builds a console terminate event for a finished Drush command.
   */
  private function terminateEvent(?string $indexId, string $commandName = 'search-api:clear', int $exitCode = 0): ConsoleTerminateEvent {
    $input = $this->createMock(InputInterface::class);
    $input->method('getArgument')->with('indexId')->willReturn($indexId);
    return new ConsoleTerminateEvent(
      new Command($commandName),
      $input,
      $this->createMock(OutputInterface::class),
      $exitCode,
    );
  }

  /**
   * Builds a console event for a Drush command.
   */
  private function event(?string $indexId, string $commandName = 'search-api:clear'): ConsoleCommandEvent {
    $input = $this->createMock(InputInterface::class);
    $input->method('getArgument')->with('indexId')->willReturn($indexId);
    return new ConsoleCommandEvent(
      new Command($commandName),
      $input,
      $this->createMock(OutputInterface::class),
    );
  }

  /**
   * Builds an index with the given id and enabled state.
   */
  private function index(string $id, bool $enabled): IndexInterface&MockObject {
    $index = $this->createMock(IndexInterface::class);
    $index->method('id')->willReturn($id);
    $index->method('status')->willReturn($enabled);
    return $index;
  }

}

/**
 * Exposes the confirmation result without calling Drush IO.
 */
final class SearchApiClearListenerUnderTest extends SearchApiClearListener {

  /**
   * How many times the confirmation was requested.
   */
  public int $confirmCalls = 0;

  /**
   * The confirmation question from the last request.
   */
  public string $confirmQuestion = '';

  /**
   * The answer the stand-in confirmation returns.
   */
  public bool $confirmResult = TRUE;

  /**
   * The entity type manager passed to the listener.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  public EntityTypeManagerInterface $entityTypeManager;

  /**
   * The logger passed to the listener.
   *
   * @var \Psr\Log\LoggerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  public LoggerInterface $logger;

  /**
   * The PubSub manager passed to the listener.
   *
   * @var \Drupal\helfi_api_base\Azure\PubSub\PubSubManagerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  public PubSubManagerInterface $pubSubManager;

  public function __construct(
    RestrictedSearchIndex $restrictedSearchIndex,
    EntityTypeManagerInterface&MockObject $entityTypeManager,
    LoggerInterface&MockObject $logger,
    PubSubManagerInterface&MockObject $pubSubManager,
    EnvironmentResolverInterface $environmentResolver,
  ) {
    parent::__construct($restrictedSearchIndex, $entityTypeManager, $logger, $pubSubManager, $environmentResolver);
    $this->entityTypeManager = $entityTypeManager;
    $this->logger = $logger;
    $this->pubSubManager = $pubSubManager;
  }

  /**
   * Records the question and returns the configured answer.
   */
  protected function confirmClear(string $question, InputInterface $input, OutputInterface $output): bool {
    $this->confirmCalls++;
    $this->confirmQuestion = $question;
    return $this->confirmResult;
  }

}
