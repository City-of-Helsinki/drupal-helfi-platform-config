<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Hook;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\helfi_search\DocumentKeyTrait;
use Drupal\helfi_search\Queue\QueueManager;
use Drupal\helfi_search\Hook\DTO\VectorizedEntityType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Keeps the embedding vectors up to date.
 *
 * @phpstan-import-type Matcher from \Drupal\helfi_search\Hook\DTO\VectorizedEntityType
 */
final class EmbeddingHooks {

  use DocumentKeyTrait;

  /**
   * The entity types allowed to produce vector embeddings.
   *
   * @var \Drupal\helfi_search\Hook\DTO\VectorizedEntityType[]
   */
  private readonly array $entityTypes;

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\helfi_search\Queue\QueueManager $queueManager
   *   The queue manager.
   * @param list<Matcher> $entityTypes
   *   The configured entity type matchers.
   */
  public function __construct(
    private readonly Connection $database,
    private readonly QueueManager $queueManager,
    #[Autowire(param: 'helfi_search.vectorized_entity_types')]
    array $entityTypes = [],
  ) {
    $this->entityTypes = array_map(
      VectorizedEntityType::fromArray(...),
      $entityTypes,
    );
  }

  /**
   * Implements hook_entity_insert().
   */
  #[Hook('entity_insert')]
  public function onEntityInsert(EntityInterface $entity): void {
    if (!$this->isVectorized($entity)) {
      return;
    }

    assert($entity instanceof ContentEntityInterface);
    $this->queueManager->markForProcessing($entity);
  }

  /**
   * Implements hook_entity_update().
   */
  #[Hook('entity_update')]
  public function onEntityUpdate(EntityInterface $entity): void {
    if (!$this->isVectorized($entity)) {
      return;
    }

    assert($entity instanceof ContentEntityInterface);
    $this->queueManager->markForProcessing($entity);
  }

  /**
   * Implements hook_entity_delete().
   */
  #[Hook('entity_delete')]
  public function onEntityDelete(EntityInterface $entity): void {
    if (!$this->isVectorized($entity)) {
      return;
    }

    $this->deleteDocument($entity);
  }

  /**
   * Implements hook_entity_translation_delete().
   */
  #[Hook('entity_translation_delete')]
  public function onEntityTranslationDelete(EntityInterface $translation): void {
    if (!$this->isVectorized($translation)) {
      return;
    }

    $this->deleteDocument($translation, translation: TRUE);
  }

  /**
   * Whether the entity should produce vector embeddings.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   */
  private function isVectorized(EntityInterface $entity): bool {
    if (!$entity instanceof ContentEntityInterface) {
      return FALSE;
    }

    return array_any(
      $this->entityTypes,
      static fn (VectorizedEntityType $type) => $type->matches($entity),
    );
  }

  /**
   * Cleans up document's chunks and state.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being removed.
   * @param bool $translation
   *   FALSE to remove every translation.
   *
   * @throws \Exception
   */
  private function deleteDocument(EntityInterface $entity, bool $translation = FALSE): void {
    $transaction = $this->database->startTransaction();

    try {
      foreach ([self::CHUNK_TABLE, self::DOCUMENT_TABLE] as $table) {
        $query = $this->database->delete($table)
          ->condition('entity_type', $entity->getEntityTypeId())
          ->condition('entity_id', (string) $entity->id());

        if ($translation) {
          $query->condition('langcode', $entity->language()->getId());
        }

        $query->execute();
      }
    }
    catch (\Exception $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

}
