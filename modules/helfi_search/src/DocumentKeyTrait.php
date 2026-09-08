<?php

declare(strict_types=1);

namespace Drupal\helfi_search;

use Drupal\Core\Database\Query\ConditionInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Helpers for accessing the embedding store's tables.
 *
 * @phpstan-type DocumentKey array{entity_type: string, entity_id: string, langcode: string}
 */
trait DocumentKeyTrait {

  /**
   * The chunk table.
   */
  public const string CHUNK_TABLE = 'helfi_search_chunk';

  /**
   * The document table.
   */
  public const string DOCUMENT_TABLE = 'helfi_search_document';

  /**
   * The primary key to the store's tables for an entity translation.
   *
   * @return DocumentKey
   *   The table key columns.
   */
  private static function key(EntityInterface $entity): array {
    return [
      'entity_type' => $entity->getEntityTypeId(),
      'entity_id' => (string) $entity->id(),
      'langcode' => $entity->language()->getId(),
    ];
  }

  /**
   * A string from the primary key for keying arrays by a document.
   *
   * @param \Drupal\Core\Entity\EntityInterface|DocumentKey $condition
   *   The entity or column values.
   */
  private static function keyId(EntityInterface|array $condition): string {
    if ($condition instanceof EntityInterface) {
      $condition = self::key($condition);
    }

    return implode(':', $condition);
  }

  /**
   * Adds primary key filters to the given query.
   *
   * @param \Drupal\Core\Database\Query\ConditionInterface $query
   *   The query to add the conditions to.
   * @param \Drupal\Core\Entity\EntityInterface|DocumentKey $condition
   *   The entity or column values.
   *
   * @template T of \Drupal\Core\Database\Query\ConditionInterface
   * @phpstan-param T $query
   * @phpstan-return T
   */
  private static function keyCondition(ConditionInterface $query, EntityInterface|array $condition): ConditionInterface {
    if ($condition instanceof EntityInterface) {
      $condition = self::key($condition);
    }

    foreach ($condition as $column => $value) {
      $query->condition($column, $value);
    }

    return $query;
  }

}
