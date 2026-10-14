<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\Drush\Listeners;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_api_base\Environment\ProjectMetadata;
use Drupal\helfi_platform_config\Drush\Listeners\SearchApiClearListener;
use Drupal\helfi_platform_config\RestrictedSearchIndex;
use Drupal\search_api\IndexInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Unit tests for the sapi-c command listener.
 */
#[Group('helfi_platform_config')]
final class SearchApiClearListenerTest extends UnitTestCase {

  /**
   * A named index outside the restricted list is cleared without a prompt.
   */
  public function testAllowsUnrestrictedIndex(): void {
    $listener = $this->listener(isEtusivu: FALSE);
    $listener->entityTypeManager->expects($this->never())->method('getStorage');
    $listener->logger->expects($this->never())->method('error');
    $event = $this->event('hyte');

    $listener->onSearchApiClear($event);

    $this->assertTrue($event->commandShouldRun());
    $this->assertSame(0, $listener->confirmCalls);
  }

  /**
   * Other commands are left alone.
   */
  public function testIgnoresOtherCommands(): void {
    $listener = $this->listener(isEtusivu: FALSE);
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
    $listener = $this->listener(isEtusivu: FALSE);
    $listener->logger->expects($this->once())
      ->method('error')
      ->with('Cannot clear the shared search index embeddings from this instance. Clear it on etusivu.');
    $event = $this->event('embeddings');

    $listener->onSearchApiClear($event);

    $this->assertFalse($event->commandShouldRun());
    $this->assertSame(0, $listener->confirmCalls);
  }

  /**
   * On etusivu, a confirmed clear of a shared index proceeds.
   */
  public function testConfirmsRestrictedIndexOnEtusivu(): void {
    $listener = $this->listener(isEtusivu: TRUE);
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
    $listener = $this->listener(isEtusivu: TRUE);
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
      isEtusivu: FALSE,
      indexes: [
        $this->index('news', TRUE),
        $this->index('embeddings', TRUE),
        $this->index('suggestions', TRUE),
      ],
    );
    $listener->logger->expects($this->once())
      ->method('error')
      ->with('Cannot clear the shared search indexes embeddings, suggestions from this instance. Clear them on etusivu.');
    $event = $this->event(NULL);

    $listener->onSearchApiClear($event);

    $this->assertFalse($event->commandShouldRun());
  }

  /**
   * A disabled restricted index is not part of a clear with no id.
   */
  public function testAllowsClearAllWhenRestrictedIndexIsDisabled(): void {
    $listener = $this->listener(
      isEtusivu: FALSE,
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
      isEtusivu: TRUE,
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
    $this->assertSame('The search indexes embeddings, suggestions are in shared use. Clear them anyway?', $listener->confirmQuestion);
  }

  /**
   * Builds the listener with a stand-in for the confirmation prompt.
   *
   * @param bool $isEtusivu
   *   Whether the active project is etusivu.
   * @param \Drupal\search_api\IndexInterface[] $indexes
   *   Indexes returned when sapi-c is run with no id.
   */
  private function listener(bool $isEtusivu, array $indexes = []): SearchApiClearListenerUnderTest {
    $resolver = $this->createMock(EnvironmentResolverInterface::class);
    $resolver->method('getActiveProject')->willReturn(new Project(
      $isEtusivu ? Project::ETUSIVU : Project::LIIKENNE,
      new ProjectMetadata('https://github.com/city-of-helsinki/drupal-helfi-etusivu'),
    ));

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadMultiple')->willReturn($indexes);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('search_api_index')->willReturn($storage);

    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $moduleHandler->method('moduleExists')->with('search_api')->willReturn(TRUE);

    return new SearchApiClearListenerUnderTest(
      new RestrictedSearchIndex($resolver),
      $entityTypeManager,
      $moduleHandler,
      $this->createMock(LoggerInterface::class),
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

  public function __construct(
    RestrictedSearchIndex $restrictedSearchIndex,
    EntityTypeManagerInterface&MockObject $entityTypeManager,
    ModuleHandlerInterface $moduleHandler,
    LoggerInterface&MockObject $logger,
  ) {
    parent::__construct($restrictedSearchIndex, $entityTypeManager, $moduleHandler, $logger);
    $this->entityTypeManager = $entityTypeManager;
    $this->logger = $logger;
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
