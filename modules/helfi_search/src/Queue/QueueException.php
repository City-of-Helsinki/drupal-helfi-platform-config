<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Queue;

/**
 * Exception thrown when a document's pipeline run fails.
 *
 * The failure been recorded. The queue item should be completed. Cron
 * hook will re-queue failed items.
 *
 * @see \Drupal\helfi_search\Queue\QueueManager::process()
 */
class QueueException extends \RuntimeException {
}
