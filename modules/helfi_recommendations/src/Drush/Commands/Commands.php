<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations\Drush\Commands;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\helfi_recommendations\Client\ApiClient;
use Drupal\helfi_recommendations\ReferenceUpdater;
use Drupal\helfi_recommendations\TopicsManagerInterface;
use Drush\Attributes\Argument;
use Drush\Attributes\Command;
use Drush\Attributes\Option;
use Drush\Attributes\Usage;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * A Drush command file.
 */
final class Commands extends DrushCommands {

  use AutowireTrait;
  use StringTranslationTrait;
  use DependencySerializationTrait;

  public function __construct(
    private readonly Connection $connection,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TopicsManagerInterface $topicsManager,
    private readonly ReferenceUpdater $referenceManager,
  ) {
    parent::__construct();
  }

  /**
   * Generate keyword to entities.
   *
   * @param string $entityType
   *   The entity type.
   * @param string $bundle
   *   The entity bundle.
   * @param array $options
   *   The command options.
   *
   * @return int
   *   The exit code.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  #[Command(name: 'helfi:recommendations:generate-keywords')]
  #[Argument(name: 'entityType', description: 'Entity type')]
  #[Argument(name: 'bundle', description: 'Entity bundle')]
  #[Option(name: 'overwrite', description: 'Overwrites existing keywords (use with caution)')]
  #[Option(name: 'batch-size', description: 'Batch size')]
  #[Usage(name: 'drush helfi:recommendations:generate-keywords node news_item', description: 'Generate keywords for news items.')]
  public function process(
    string $entityType,
    string $bundle,
    array $options = [
      'overwrite' => FALSE,
      'batch-size' => ApiClient::MAX_BATCH_SIZE,
    ],
  ) : int {
    $definition = $this->entityTypeManager->getDefinition($entityType);

    if (!$definition) {
      $this->io()->writeln('Given entity type is not supported.');
      return DrushCommands::EXIT_FAILURE;
    }

    $query = $this->connection
      ->select($definition->getBaseTable(), 't')
      ->fields('t', [$definition->getKey('id')]);

    if ($definition->hasKey('bundle')) {
      $query->condition('t.' . $definition->getKey('bundle'), $bundle);
    }
    $entityIds = $query
      ->execute()
      ->fetchCol();

    $batch = (new BatchBuilder())
      ->addOperation([$this, 'processBatch'], [
        $entityType,
        $options['batch-size'],
        $options['overwrite'],
        $entityIds,
      ]);

    batch_set($batch->toArray());

    drush_backend_batch_process();

    return DrushCommands::EXIT_SUCCESS;
  }

  /**
   * Processes a batch operation.
   */
  public function processBatch(
    string $entityType,
    ?int $batchSize,
    bool $overwrite,
    array $entityIds,
    &$context,
  ) : void {
    [$slice,, $to] = $this->initBatchParams($context, $entityIds, $batchSize ?? 0);

    try {
      $entities = $this->entityTypeManager
        ->getStorage($entityType)
        ->loadMultiple($slice);

      $this->topicsManager->processEntities($entities, $overwrite, TRUE);

      $this->updateBatchParams($context, $entityIds, $to);
    }
    catch (\Exception $e) {
      $context['message'] = $this->t('An error occurred during processing: @message', ['@message' => $e->getMessage()]);
      $context['finished'] = 1;
    }
  }

  /**
   * Fix entity references in a batch.
   *
   * @param array $entityIds
   *   Ids of entities to update.
   * @param array $context
   *   The context.
   */
  public function batchFixEntityReferences(array $entityIds, array &$context): void {
    $batchSize = 50;
    [$slice,, $to] = $this->initBatchParams($context, $entityIds, $batchSize);

    try {
      foreach ($slice as $item) {
        ['entity_type' => $entity_type, 'id' => $id] = $item;

        $entity = $this->entityTypeManager
          ->getStorage($entity_type)
          ->load($id);

        assert($entity instanceof FieldableEntityInterface);
        $this->referenceManager->updateEntityReferenceFields($entity);
      }

      $this->updateBatchParams($context, $entityIds, $to);
    }
    catch (\Exception $e) {
      $context['message'] = sprintf('An error occurred during processing: %s', $e->getMessage());
      $context['finished'] = 1;
    }
  }

  /**
   * Set new fields' default values.
   */
  #[Command(name: 'helfi:recommendations:fix-references')]
  public function fixEntityReferences(): int {
    $entities = $this->referenceManager->getReferencesWithoutTarget();

    $batch = (new BatchBuilder())
      ->addOperation([$this, 'batchFixEntityReferences'], [
        $entities,
      ]);

    batch_set($batch->toArray());

    drush_backend_batch_process();

    return DrushCommands::EXIT_SUCCESS;
  }

  /**
   * Get batch params.
   */
  private function initBatchParams(array &$context, array $items, int $batchSize): array {
    // Check if the sandbox should be initialized.
    if (!isset($context['sandbox']['from'])) {
      $context['sandbox']['from'] = 0;
    }

    $from = $context['sandbox']['from'];
    $to = min($from + $batchSize, count($items));
    $slice = array_slice($items, $from, $to - $from);

    return [$slice, $from, $to];
  }

  /**
   * Update batch params.
   */
  private function updateBatchParams(array &$context, array $items, int $to): void {
    $context['sandbox']['from'] = $to;
    $context['message'] = sprintf("%d items remaining", count($items) - $to);
    $context['finished'] = $to >= count($items);
  }

}
