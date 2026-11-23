<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\Drush\Listeners;

use ColinODell\PsrTestLogger\TestLogger;
use Drupal\helfi_api_base\Azure\PubSub\PubSubManagerInterface;
use Drupal\helfi_api_base\Azure\PubSub\Settings;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_platform_config\Drush\Listeners\SearchApiClearListener;
use Drupal\helfi_platform_config\SearchAPI\RestrictedSearchIndex;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\Tests\helfi_platform_config\Kernel\KernelTestBase;
use Drupal\Tests\helfi_platform_config\Traits\SharedSearchIndexTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Tests the drush search-api:clear guard and the reindex notification.
 */
#[Group('helfi_platform_config')]
#[RunTestsInSeparateProcesses]
final class SearchApiClearListenerTest extends KernelTestBase {

  use EnvironmentResolverTrait;
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
   * The logger passed to the listener.
   */
  private TestLogger $logger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setUpSearchApi();
    $this->logger = new TestLogger();
  }

  /**
   * An index that is not shared is cleared without a prompt.
   */
  public function testAllowsIndexThatIsNotShared(): void {
    $this->setActiveProject(Project::LIIKENNE, EnvironmentEnum::Local);
    $this->createIndex('news');
    $listener = $this->listener();
    $event = $this->commandEvent('news');

    $listener->onSearchApiClear($event);

    $this->assertTrue($event->commandShouldRun());
    $this->assertSame(0, $listener->confirmCalls);
    $this->assertFalse($this->logger->hasErrorRecords());
  }

  /**
   * Other commands are left alone.
   */
  public function testIgnoresOtherCommands(): void {
    $this->setActiveProject(Project::LIIKENNE, EnvironmentEnum::Local);
    $listener = $this->listener();
    $event = $this->commandEvent('embeddings', 'search-api:index');

    $listener->onSearchApiClear($event);

    $this->assertTrue($event->commandShouldRun());
    $this->assertSame(0, $listener->confirmCalls);
  }

  /**
   * A shared index cannot be cleared outside its main instance.
   */
  public function testBlocksSharedIndexOffMainInstance(): void {
    $this->setActiveProject(Project::LIIKENNE, EnvironmentEnum::Local);
    $this->createIndex('embeddings');
    $listener = $this->listener();
    $event = $this->commandEvent('embeddings');

    $listener->onSearchApiClear($event);

    $this->assertFalse($event->commandShouldRun());
    $this->assertSame(0, $listener->confirmCalls);
    $this->assertTrue($this->logger->hasError("Cannot clear the following shared indexes here:\n- embeddings, clear on etusivu"));
  }

  /**
   * A confirmed clear of a shared index proceeds on its main instance.
   */
  public function testConfirmsSharedIndexOnMainInstance(): void {
    $this->setActiveProject(Project::ETUSIVU, EnvironmentEnum::Local);
    $this->createIndex('suggestions');
    $listener = $this->listener();
    $event = $this->commandEvent('suggestions');

    $listener->onSearchApiClear($event);

    $this->assertTrue($event->commandShouldRun());
    $this->assertSame(1, $listener->confirmCalls);
    $this->assertSame('The search index suggestions is in shared use. Clear it anyway?', $listener->confirmQuestion);
    $this->assertFalse($this->logger->hasErrorRecords());
  }

  /**
   * Declining the confirmation disables the clear.
   */
  public function testAbortsWhenConfirmationIsDeclined(): void {
    $this->setActiveProject(Project::ETUSIVU, EnvironmentEnum::Local);
    $this->createIndex('embeddings');
    $listener = $this->listener();
    $listener->confirmResult = FALSE;
    $event = $this->commandEvent('embeddings');

    $listener->onSearchApiClear($event);

    $this->assertFalse($event->commandShouldRun());
    $this->assertTrue($this->logger->hasError('The search index embeddings is in shared use. Clear it anyway?'));
  }

  /**
   * Clearing every index is blocked when it includes enabled shared indexes.
   *
   * The message lists each shared index with its own main instance.
   */
  public function testBlocksClearAllWithEnabledSharedIndexes(): void {
    $this->setActiveProject(Project::LIIKENNE, EnvironmentEnum::Local);
    $this->createIndex('news');
    $this->createIndex('embeddings');
    $this->createIndex('hyte');
    $event = $this->commandEvent(NULL);

    $this->listener()->onSearchApiClear($event);

    $this->assertFalse($event->commandShouldRun());
    $this->assertTrue($this->logger->hasError("Cannot clear the following shared indexes here:\n- embeddings, clear on etusivu\n- hyte, clear on asuminen"));
  }

  /**
   * Search API skips disabled indexes, so a disabled shared index is allowed.
   */
  public function testAllowsClearAllWithDisabledSharedIndex(): void {
    $this->setActiveProject(Project::LIIKENNE, EnvironmentEnum::Local);
    $this->createIndex('news');
    $this->createIndex('embeddings', FALSE);
    $listener = $this->listener();
    $event = $this->commandEvent(NULL);

    $listener->onSearchApiClear($event);

    $this->assertTrue($event->commandShouldRun());
    $this->assertSame(0, $listener->confirmCalls);
  }

  /**
   * Clearing every index on the main instance asks once for all of them.
   */
  public function testConfirmsClearAllOnMainInstance(): void {
    $this->setActiveProject(Project::ETUSIVU, EnvironmentEnum::Local);
    $this->createIndex('suggestions');
    $this->createIndex('news');
    $this->createIndex('embeddings');
    $listener = $this->listener();
    $event = $this->commandEvent(NULL);

    $listener->onSearchApiClear($event);

    $this->assertTrue($event->commandShouldRun());
    $this->assertSame(1, $listener->confirmCalls);
    $this->assertSame('The search indexes embeddings, suggestions are in shared use. Clear them anyway?', $listener->confirmQuestion);
  }

  /**
   * A successful clear of a shared index asks other instances to reindex.
   */
  public function testSuccessfulClearSendsReindexMessage(): void {
    $this->setActiveProject(Project::ETUSIVU, EnvironmentEnum::Local);
    $this->createIndex('embeddings');

    $this->listener()->onSearchApiClearTerminate($this->terminateEvent('embeddings'));

    $messages = $this->sentMessages();
    $this->assertCount(1, $messages);
    $this->assertSame(['index' => 'embeddings', 'instance' => Project::ETUSIVU], $messages[0]['search_api_reindex']);
    $sendFrames = array_filter($this->sentFrames, static fn (array $frame): bool => $frame['type'] === 'sendToGroup');
    $this->assertSame(['shared'], array_column($sendFrames, 'group'));
    $this->assertTrue($this->logger->hasNotice('Asked other instances to reindex the shared index embeddings.'));
  }

  /**
   * Nothing is sent after a failed or disabled clear, or another command.
   */
  public function testNothingSentWithoutSuccessfulClear(): void {
    $this->setActiveProject(Project::ETUSIVU, EnvironmentEnum::Local);
    $this->createIndex('embeddings');
    $listener = $this->listener();

    $listener->onSearchApiClearTerminate($this->terminateEvent('embeddings', exitCode: ConsoleCommandEvent::RETURN_CODE_DISABLED));
    $listener->onSearchApiClearTerminate($this->terminateEvent('embeddings', exitCode: 1));
    $listener->onSearchApiClearTerminate($this->terminateEvent('embeddings', 'search-api:index'));

    $this->assertSame([], $this->sentFrames);
  }

  /**
   * Nothing is sent for indexes that are not shared, disabled or missing.
   */
  public function testNothingSentForIndexesThatWereNotCleared(): void {
    $this->setActiveProject(Project::ETUSIVU, EnvironmentEnum::Local);
    $this->createIndex('news');
    $this->createIndex('suggestions', FALSE);
    $listener = $this->listener();

    $listener->onSearchApiClearTerminate($this->terminateEvent('news'));
    $listener->onSearchApiClearTerminate($this->terminateEvent('suggestions'));
    $listener->onSearchApiClearTerminate($this->terminateEvent('hyte'));

    $this->assertSame([], $this->sentFrames);
  }

  /**
   * Clearing every index sends one message per cleared shared index.
   */
  public function testClearAllSendsMessagePerClearedSharedIndex(): void {
    $this->setActiveProject(Project::ASUMINEN, EnvironmentEnum::Local);
    $this->createIndex('news');
    $this->createIndex('embeddings');
    $this->createIndex('suggestions', FALSE);
    $this->createIndex('hyte');

    $this->listener()->onSearchApiClearTerminate($this->terminateEvent(NULL));

    $this->assertSame([
      ['index' => 'embeddings', 'instance' => Project::ASUMINEN],
      ['index' => 'hyte', 'instance' => Project::ASUMINEN],
    ], array_column($this->sentMessages(), 'search_api_reindex'));
  }

  /**
   * Nothing is sent when the sending instance is unknown.
   */
  public function testNothingSentWhenActiveProjectIsUnknown(): void {
    $this->createIndex('embeddings');

    $this->listener()->onSearchApiClearTerminate($this->terminateEvent('embeddings'));

    $this->assertSame([], $this->sentFrames);
    $this->assertTrue($this->logger->hasWarning('Other instances were not asked to reindex, because the active project is unknown.'));
  }

  /**
   * Missing PubSub configuration is logged and does not fail the command.
   */
  public function testUnconfiguredPubSubIsLogged(): void {
    $this->setActiveProject(Project::ETUSIVU, EnvironmentEnum::Local);
    $this->createIndex('embeddings');
    $listener = $this->listener($this->pubSubManager(settings: new Settings('', '', '', [])));

    $listener->onSearchApiClearTerminate($this->terminateEvent('embeddings'));

    $this->assertSame([], $this->sentFrames);
    $this->assertTrue($this->logger->hasWarning('Other instances were not asked to reindex the shared index embeddings: PubSub access key is undefined.'));
  }

  /**
   * A clear on one instance marks the items for reindexing on another.
   */
  public function testClearedIndexIsReindexedOnOtherInstance(): void {
    $this->createIndex('embeddings');
    $this->createIndexedItems(2);
    $this->assertSame(0, $this->remainingItems('embeddings'));

    // Send as etusivu. The sender's own resolver keeps the project it
    // resolved first.
    $etusivu = $this->listener(environmentResolver: $this->getEnvironmentResolver(Project::ETUSIVU, EnvironmentEnum::Local));
    $etusivu->onSearchApiClearTerminate($this->terminateEvent('embeddings'));
    $messages = $this->sentMessages();
    $this->assertCount(1, $messages);

    // Receive as asuminen, through the site's own services.
    $this->setActiveProject(Project::ASUMINEN, EnvironmentEnum::Local);
    $this->pubSubManager([$this->deliveredFrame($messages[0])])->receive();

    $this->assertSame(2, $this->remainingItems('embeddings'));
  }

  /**
   * Builds the listener with real services and a recording PubSub client.
   */
  private function listener(?PubSubManagerInterface $pubSubManager = NULL, ?EnvironmentResolverInterface $environmentResolver = NULL): SearchApiClearListenerUnderTest {
    $environmentResolver ??= $this->container->get(EnvironmentResolverInterface::class);
    return new SearchApiClearListenerUnderTest(
      new RestrictedSearchIndex($environmentResolver),
      $this->container->get('entity_type.manager'),
      $this->logger,
      $pubSubManager ?? $this->pubSubManager(),
      $environmentResolver,
    );
  }

  /**
   * Builds the event dispatched before a Drush command runs.
   */
  private function commandEvent(?string $indexId, string $commandName = 'search-api:clear'): ConsoleCommandEvent {
    return new ConsoleCommandEvent(new Command($commandName), $this->input($indexId), new NullOutput());
  }

  /**
   * Builds the event dispatched after a Drush command finished.
   */
  private function terminateEvent(?string $indexId, string $commandName = 'search-api:clear', int $exitCode = 0): ConsoleTerminateEvent {
    return new ConsoleTerminateEvent(new Command($commandName), $this->input($indexId), new NullOutput(), $exitCode);
  }

  /**
   * Builds the command input with an optional index id argument.
   */
  private function input(?string $indexId): InputInterface {
    return new ArrayInput(
      $indexId === NULL ? [] : ['indexId' => $indexId],
      new InputDefinition([new InputArgument('indexId', InputArgument::OPTIONAL)]),
    );
  }

}

/**
 * Answers the confirmation prompt without Drush IO.
 *
 * DrushStyle::confirm() needs the Drush runtime, which kernel tests lack.
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
   * The answer the confirmation returns.
   */
  public bool $confirmResult = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function confirmClear(string $question, InputInterface $input, OutputInterface $output): bool {
    $this->confirmCalls++;
    $this->confirmQuestion = $question;
    return $this->confirmResult;
  }

}
