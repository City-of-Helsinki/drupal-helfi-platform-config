<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Queue\DTO;

/**
 * DTO representing an entity translation in the embedding queue.
 *
 * @see \Drupal\helfi_search\Queue\QueueManager
 *
 * @phpstan-import-type DocumentKey from \Drupal\helfi_search\DocumentKeyTrait
 */
final readonly class ClaimedDocument {

  public function __construct(
    public string $entityType,
    public string $entityId,
    public string $langcode,
    public int $changed,
  ) {
  }

  /**
   * The claimed document's primary key in the embedding store.
   *
   * @return DocumentKey
   *   The key columns, keyed by column name.
   */
  public function key(): array {
    return [
      'entity_type' => $this->entityType,
      'entity_id' => $this->entityId,
      'langcode' => $this->langcode,
    ];
  }

}
