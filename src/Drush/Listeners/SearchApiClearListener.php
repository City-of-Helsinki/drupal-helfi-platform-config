<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Drush\Listeners;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\helfi_platform_config\RestrictedClearAction;
use Drupal\helfi_platform_config\RestrictedSearchIndex;
use Drupal\search_api\IndexInterface;
use Drush\Commands\AutowireTrait;
use Drush\Style\DrushStyle;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Stops drush sapi-c from clearing shared search indexes.
 *
 * Discovered from src/Drush/Listeners after the full Drupal bootstrap, because
 * AutowireTrait::create() postpones listeners that need Drupal services.
 */
#[AsEventListener(method: 'onSearchApiClear')]
class SearchApiClearListener {

  use AutowireTrait;

  public function __construct(
    private readonly RestrictedSearchIndex $restrictedSearchIndex,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly LoggerInterface $logger,
  ) {
  }

  /**
   * Blocks or confirms a clear of shared search indexes.
   *
   * On instances other than etusivu the command is disabled. On etusivu it
   * asks for confirmation.
   * The default answer is no, so a non-interactive run is disabled unless
   * --yes is passed. DrushStyle::confirm() accepts --yes before the prompt.
   */
  public function onSearchApiClear(ConsoleCommandEvent $event): void {
    if ($event->getCommand()?->getName() !== 'search-api:clear') {
      return;
    }

    $indexId = $event->getInput()->getArgument('indexId');
    $candidates = $this->candidateIds(is_string($indexId) && $indexId !== '' ? $indexId : NULL);
    $targets = $this->restrictedSearchIndex->matchingIds($candidates);
    $action = $this->restrictedSearchIndex->actionFor($candidates);

    if ($action === RestrictedClearAction::Allow) {
      return;
    }
    if ($action === RestrictedClearAction::Block) {
      $this->logger->error($this->blockMessage($targets));
      $event->disableCommand();
      return;
    }

    $question = $this->confirmMessage($targets);
    if (!$this->confirmClear($question, $event->getInput(), $event->getOutput())) {
      $this->logger->error($question);
      $event->disableCommand();
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
    if (!$this->moduleHandler->moduleExists('search_api')) {
      return [];
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
   * Message shown when this instance must not clear the indexes.
   *
   * @param string[] $targets
   *   Restricted index ids.
   */
  private function blockMessage(array $targets): string {
    if (count($targets) === 1) {
      return sprintf('Cannot clear the shared search index %s from this instance. Clear it on etusivu.', $targets[0]);
    }
    return sprintf('Cannot clear the shared search indexes %s from this instance. Clear them on etusivu.', implode(', ', $targets));
  }

  /**
   * Confirmation question for a clear on etusivu.
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
