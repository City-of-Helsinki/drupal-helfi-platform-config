<?php

declare(strict_types=1);

namespace Drupal\helfi_entity_task;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;

/**
 * Stores tasks to do on entity translations.
 */
class EntityTaskStorage {

  private const string TABLE = 'helfi_entity_task';

  public function __construct(
    private readonly Connection $connection,
    private readonly TimeInterface $time,
    private readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
  ) {}

  /**
   * Marks the task pending for the entity translation.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity translation.
   * @param string $task
   *   The task ID.
   */
  public function mark(EntityInterface $entity, string $task): void {
    $this->setStatus($entity, $task, EntityTaskStatus::Pending);
  }

  /**
   * Marks the task done for the entity translation.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity translation.
   * @param string $task
   *   The task ID.
   */
  public function complete(EntityInterface $entity, string $task): void {
    $this->setStatus($entity, $task, EntityTaskStatus::Done);
  }

  /**
   * Checks whether the task is pending for the entity translation.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity translation.
   * @param string $task
   *   The task ID.
   *
   * @return bool
   *   TRUE if the task is pending.
   */
  public function isPending(EntityInterface $entity, string $task): bool {
    return ($this->loadRow($entity, $task)['status'] ?? NULL) === EntityTaskStatus::Pending;
  }

  /**
   * Checks whether the task was completed at or after the given time.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity translation.
   * @param string $task
   *   The task ID.
   * @param int $timestamp
   *   The timestamp.
   *
   * @return bool
   *   TRUE if the task is done and was completed at or after the timestamp.
   */
  public function isCompletedSince(EntityInterface $entity, string $task, int $timestamp): bool {
    $row = $this->loadRow($entity, $task);

    return $row !== NULL
      && $row['status'] === EntityTaskStatus::Done
      && $row['updated'] >= $timestamp;
  }

  /**
   * Gets the cache tags of the entity's tasks.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return string[]
   *   The cache tags.
   */
  public function getCacheTags(EntityInterface $entity): array {
    return ['helfi_entity_task:' . $entity->getEntityTypeId() . ':' . $entity->id()];
  }

  /**
   * Loads the row of the task.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity translation.
   * @param string $task
   *   The task ID.
   *
   * @return array|null
   *   The row with status and updated, or NULL if there is none.
   *
   * @phpstan-return array{status: \Drupal\helfi_entity_task\EntityTaskStatus, updated: int}|null
   */
  private function loadRow(EntityInterface $entity, string $task): ?array {
    $query = $this->connection->select(self::TABLE, 't')
      ->fields('t', ['status', 'updated']);

    foreach ($this->getKeys($entity, $task) as $field => $value) {
      $query->condition($field, $value);
    }
    $row = $query->execute()?->fetchAssoc();

    if (!$row) {
      return NULL;
    }
    return [
      'status' => EntityTaskStatus::from($row['status']),
      'updated' => (int) $row['updated'],
    ];
  }

  /**
   * Sets the status of the task.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity translation.
   * @param string $task
   *   The task ID.
   * @param \Drupal\helfi_entity_task\EntityTaskStatus $status
   *   The status.
   */
  private function setStatus(EntityInterface $entity, string $task, EntityTaskStatus $status): void {
    $this->connection->merge(self::TABLE)
      ->keys($this->getKeys($entity, $task))
      ->fields([
        'status' => $status->value,
        'updated' => $this->time->getRequestTime(),
      ])
      ->execute();
    $this->cacheTagsInvalidator->invalidateTags($this->getCacheTags($entity));
  }

  /**
   * Gets the table keys.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity translation.
   * @param string $task
   *   The task ID.
   *
   * @return array<string, mixed>
   *   The keys.
   */
  private function getKeys(EntityInterface $entity, string $task): array {
    return [
      'entity_type' => $entity->getEntityTypeId(),
      'entity_id' => $entity->id(),
      'langcode' => $entity->language()->getId(),
      'task' => $task,
    ];
  }

}
