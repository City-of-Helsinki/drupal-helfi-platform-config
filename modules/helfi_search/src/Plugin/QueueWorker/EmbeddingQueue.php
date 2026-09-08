<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker as QueueWorkerAttribute;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Error;
use Drupal\helfi_search\MissingConfigurationException;
use Drupal\helfi_search\Queue\DTO\ClaimedDocument;
use Drupal\helfi_search\Queue\QueueException;
use Drupal\helfi_search\Queue\QueueManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Generates embeddings for one document translation.
 */
#[QueueWorkerAttribute(
  id: self::class,
  title: new TranslatableMarkup('Embeddings queue'),
  cron: ['time' => 60],
)]
final class EmbeddingQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $configuration
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly QueueManager $queueManager,
    #[Autowire(service: 'logger.channel.helfi_search')]
    protected readonly LoggerInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function processItem(mixed $data): void {
    if (!$data instanceof ClaimedDocument) {
      return;
    }

    // Only run the pipeline if a queued item is still up to date.
    // This filters our items that have been queued multiple times.
    if (!$this->queueManager->isPending($data)) {
      return;
    }

    try {
      $this->logger->info('Processing @type:@id to embedding queue: @document', [
        '@type' => $data->entityType,
        '@id' => $data->entityId,
      ]);

      $this->queueManager->process($data);
    }
    catch (MissingConfigurationException $e) {
      // No point in continuing, every remaining item would fail the same way.
      throw new SuspendQueueException($e->getMessage(), previous: $e);
    }
    catch (QueueException $e) {
      // Event failures should complete the queue item. Re-try logic is
      // built into cron hook. Queue manager counts failures in the
      // database, and too many consecutive failures mark the item as
      // failed so that it is not re-tried again.
      Error::logException($this->logger, $e);
    }
  }

}
