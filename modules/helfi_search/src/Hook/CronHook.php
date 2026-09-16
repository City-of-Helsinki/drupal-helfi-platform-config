<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Queue\QueueFactory;
use Drupal\helfi_search\Plugin\QueueWorker\EmbeddingQueue;
use Drupal\helfi_search\Queue\QueueManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Feeds pending documents into the embedding queue.
 */
#[Hook('cron')]
final readonly class CronHook {

  public function __construct(
    private QueueManager $queueManager,
    private QueueFactory $queueFactory,
    #[Autowire(service: 'logger.channel.helfi_search')]
    private LoggerInterface $logger,
  ) {
  }

  /**
   * Implements hook_cron().
   */
  public function __invoke(): void {
    $documents = $this->queueManager->claimPending();

    if (!$documents) {
      return;
    }

    $queue = $this->queueFactory->get(EmbeddingQueue::class);

    foreach ($documents as $document) {
      $this->logger->info('Adding @type:@id to embedding queue: @document', [
        '@type' => $document->entityType,
        '@id' => $document->entityId,
      ]);

      $queue->createItem($document);
    }
  }

}
