<?php

declare(strict_types=1);

namespace Drupal\helfi_users;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Stores the entities users have marked as favorites.
 */
final class FavoriteStorage {

  /**
   * Entity types that can be marked as favorites.
   */
  public const array ENTITY_TYPES = [
    'node',
    'tpr_unit',
    'tpr_service',
  ];

  private const string TABLE = 'helfi_users_favorite';

  /**
   * Favorite translation keys keyed by user ID and entity type.
   *
   * @var array<string, array<string, true>>
   */
  private array $favorites = [];

  public function __construct(
    private readonly Connection $connection,
    private readonly TimeInterface $time,
    private readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
  ) {}

  /**
   * Checks whether the entity translation is a favorite of the user.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity translation.
   *
   * @return bool
   *   TRUE if the entity translation is a favorite.
   */
  public function isFavorite(AccountInterface $account, EntityInterface $entity): bool {
    $key = $account->id() . ':' . $entity->getEntityTypeId();

    // Load all favorites of the entity type once per request.
    if (!isset($this->favorites[$key])) {
      $rows = $this->connection->select(self::TABLE, 'f')
        ->fields('f', ['entity_id', 'langcode'])
        ->condition('uid', $account->id())
        ->condition('entity_type', $entity->getEntityTypeId())
        ->execute();

      $this->favorites[$key] = [];
      foreach ($rows as $row) {
        $this->favorites[$key][$row->entity_id . ':' . $row->langcode] = TRUE;
      }
    }

    return isset($this->favorites[$key][$entity->id() . ':' . $entity->language()->getId()]);
  }

  /**
   * Adds or removes the entity translation from the user's favorites.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity translation.
   *
   * @return bool
   *   TRUE if the entity translation is now a favorite.
   */
  public function toggle(AccountInterface $account, EntityInterface $entity): bool {
    if ($this->remove($account, $entity)) {
      return FALSE;
    }
    $this->add($account, $entity);
    return TRUE;
  }

  /**
   * Adds the entity translation to the user's favorites.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity translation.
   */
  public function add(AccountInterface $account, EntityInterface $entity): void {
    // Keep the original creation time of an existing favorite.
    $this->connection->merge(self::TABLE)
      ->insertFields(['created' => $this->time->getRequestTime()])
      ->keys($this->getKeys($account, $entity))
      ->execute();
    $this->clearCache($account, $entity);
  }

  /**
   * Removes the entity translation from the user's favorites.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity translation.
   *
   * @return bool
   *   TRUE if the favorite was removed.
   */
  public function remove(AccountInterface $account, EntityInterface $entity): bool {
    $query = $this->connection->delete(self::TABLE);
    foreach ($this->getKeys($account, $entity) as $field => $value) {
      $query->condition($field, $value);
    }
    if (!$query->execute()) {
      return FALSE;
    }
    $this->clearCache($account, $entity);
    return TRUE;
  }

  /**
   * Gets the favorite table keys.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity translation.
   *
   * @return array<string, mixed>
   *   The keys.
   */
  private function getKeys(AccountInterface $account, EntityInterface $entity): array {
    return [
      'uid' => $account->id(),
      'entity_type' => $entity->getEntityTypeId(),
      'entity_id' => $entity->id(),
      'langcode' => $entity->language()->getId(),
    ];
  }

  /**
   * Clears the request cache and the user's rendered favorites.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity translation.
   */
  private function clearCache(AccountInterface $account, EntityInterface $entity): void {
    unset($this->favorites[$account->id() . ':' . $entity->getEntityTypeId()]);
    $this->cacheTagsInvalidator->invalidateTags(['helfi_favorite:' . $account->id()]);
  }

  /**
   * Deletes the favorites of the entity from all users.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string|null $langcode
   *   The translation langcode or NULL for all translations.
   */
  public function deleteByEntity(EntityInterface $entity, ?string $langcode = NULL): void {
    $query = $this->connection->delete(self::TABLE)
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', $entity->id());

    if ($langcode) {
      $query->condition('langcode', $langcode);
    }
    $query->execute();
    $this->favorites = [];
  }

  /**
   * Deletes all favorites of the user.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   */
  public function deleteByUser(AccountInterface $account): void {
    $this->connection->delete(self::TABLE)
      ->condition('uid', $account->id())
      ->execute();
    $this->favorites = [];
  }

}
