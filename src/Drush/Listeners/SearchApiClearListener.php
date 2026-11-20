<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Drush\Listeners;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\helfi_api_base\Azure\PubSub\PubSubManagerInterface;
use Drupal\helfi_api_base\Environment\EnvironmentResolverException;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_platform_config\EventSubscriber\SearchApiReindexSubscriber;
use Drupal\helfi_platform_config\SearchAPI\RestrictedClearAction;
use Drupal\helfi_platform_config\SearchAPI\RestrictedSearchIndex;
use Drupal\search_api\IndexInterface;
use Drush\Commands\AutowireTrait;
use Drush\Style\DrushStyle;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use WebSocket\Exception\Exception as WebSocketException;

/**
 * Guards drush sapi-c on shared search indexes.
 *
 * Before the command, stops it from clearing shared indexes on instances that
 * must not do so. After a successful clear, tells the other instances over
 * PubSub to mark their items for reindexing, see SearchApiReindexSubscriber.
 *
 * Discovered from src/Drush/Listeners after the full Drupal bootstrap, because
 * AutowireTrait::create() postpones listeners that need Drupal services.
 */
#[AsEventListener(method: 'onSearchApiClear')]
#[AsEventListener(method: 'onSearchApiClearTerminate')]
class SearchApiClearListener {

  use AutowireTrait;

  public function __construct(
    private readonly RestrictedSearchIndex $restrictedSearchIndex,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LoggerInterface $logger,
    private readonly PubSubManagerInterface $pubSubManager,
    private readonly EnvironmentResolverInterface $environmentResolver,
  ) {
  }

  /**
   * Blocks or confirms a clear of shared search indexes.
   *
   * The main instance is asked to confirm. Every other instance is blocked.
   * The default answer is no, so a non-interactive run is disabled unless
   * --yes is passed. DrushStyle::confirm() accepts --yes before the prompt.
   */
  public function onSearchApiClear(ConsoleCommandEvent $event): void {
    if ($event->getCommand()?->getName() !== 'search-api:clear') {
      return;
    }

    $indexId = $event->getInput()->getArgument('indexId');
    $candidates = $this->candidateIds(is_string($indexId) && $indexId !== '' ? $indexId : NULL);
    $actions = $this->restrictedSearchIndex->actionFor($candidates);

    if ($this->idsFor($actions, RestrictedClearAction::Block) !== []) {
      $this->logger->error($this->blockMessage($actions));
      $event->disableCommand();
      return;
    }

    $confirmIds = $this->idsFor($actions, RestrictedClearAction::Confirm);
    if ($confirmIds === []) {
      return;
    }

    $question = $this->confirmMessage($confirmIds);
    if (!$this->confirmClear($question, $event->getInput(), $event->getOutput())) {
      $this->logger->error($question);
      $event->disableCommand();
    }
  }

  /**
   * Asks other instances to reindex shared indexes after a successful clear.
   *
   * Runs for every command, so it checks the command name and exit code. A
   * blocked or declined clear exits with a non-zero code.
   */
  public function onSearchApiClearTerminate(ConsoleTerminateEvent $event): void {
    if ($event->getCommand()?->getName() !== 'search-api:clear' || $event->getExitCode() !== 0) {
      return;
    }

    $indexId = $event->getInput()->getArgument('indexId');
    $sharedIds = array_values(array_filter(
      $this->candidateIds(is_string($indexId) && $indexId !== '' ? $indexId : NULL),
      RestrictedSearchIndex::isShared(...),
    ));
    if ($sharedIds === []) {
      return;
    }

    // Search API only clears enabled indexes, and an unknown id exits with
    // code 0 without clearing anything.
    $clearedIds = [];
    foreach ($this->entityTypeManager->getStorage('search_api_index')->loadMultiple($sharedIds) as $index) {
      if ($index instanceof IndexInterface && $index->status() && is_string($index->id())) {
        $clearedIds[] = $index->id();
      }
    }
    if ($clearedIds === []) {
      return;
    }

    try {
      $instance = $this->environmentResolver->getActiveProject()->getName();
    }
    catch (EnvironmentResolverException) {
      $this->logger->warning('Other instances were not asked to reindex, because the active project is unknown.');
      return;
    }

    foreach ($clearedIds as $clearedId) {
      try {
        $this->pubSubManager->sendMessage([
          SearchApiReindexSubscriber::MESSAGE_KEY => [
            'index' => $clearedId,
            'instance' => $instance,
          ],
        ]);
        $this->logger->notice(sprintf('Asked other instances to reindex the shared index %s.', $clearedId));
      }
      catch (WebSocketException | \JsonException | \LogicException $e) {
        $this->logger->warning(sprintf('Other instances were not asked to reindex the shared index %s: %s', $clearedId, $e->getMessage()));
      }
    }
  }

  /**
   * Asks whether to clear a shared index.
   */
  protected function confirmClear(string $question, InputInterface $input, OutputInterface $output): bool {
    return (new DrushStyle($input, $output))->confirm($question, FALSE);
  }

  /**
   * Index ids sapi-c would clear.
   *
   * A named index is the only candidate. With no id, Search API clears every
   * enabled index, so disabled restricted indexes are not candidates.
   *
   * @return string[]
   *   The index ids.
   */
  private function candidateIds(?string $indexId): array {
    if ($indexId !== NULL) {
      return [$indexId];
    }

    $ids = [];
    foreach ($this->entityTypeManager->getStorage('search_api_index')->loadMultiple() as $index) {
      if (!$index instanceof IndexInterface || !$index->status()) {
        continue;
      }
      $id = $index->id();
      if (is_string($id)) {
        $ids[] = $id;
      }
    }
    return $ids;
  }

  /**
   * Index ids whose action matches.
   *
   * @param array<string, \Drupal\helfi_platform_config\SearchAPI\RestrictedClearAction> $actions
   *   Actions keyed by index id.
   * @param \Drupal\helfi_platform_config\SearchAPI\RestrictedClearAction $expected
   *   The action to collect.
   *
   * @return string[]
   *   Matching index ids, in the given order.
   */
  private function idsFor(array $actions, RestrictedClearAction $expected): array {
    $ids = [];
    foreach ($actions as $id => $action) {
      if ($action === $expected) {
        $ids[] = $id;
      }
    }
    return $ids;
  }

  /**
   * Message shown when this instance must not clear the indexes.
   *
   * @param array<string, \Drupal\helfi_platform_config\SearchAPI\RestrictedClearAction> $actions
   *   Actions keyed by index id.
   */
  private function blockMessage(array $actions): string {
    $lines = ['Cannot clear the following shared indexes here:'];
    foreach ($this->idsFor($actions, RestrictedClearAction::Block) as $indexId) {
      $mainInstance = $this->restrictedSearchIndex->mainInstance($indexId);
      if ($mainInstance !== NULL) {
        $lines[] = sprintf('- %s, clear on %s', $indexId, $mainInstance);
      }
    }
    return implode("\n", $lines);
  }

  /**
   * Confirmation question for a clear on the main instance.
   *
   * @param string[] $targets
   *   Restricted index ids.
   */
  private function confirmMessage(array $targets): string {
    if (count($targets) === 1) {
      return sprintf('The search index %s is in shared use. Clear it anyway?', $targets[0]);
    }
    return sprintf('The search indexes %s are in shared use. Clear them anyway?', implode(', ', $targets));
  }

}
