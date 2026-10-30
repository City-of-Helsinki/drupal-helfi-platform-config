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
    $keys = [
      'uid' => $account->id(),
      'entity_type' => $entity->getEntityTypeId(),
      'entity_id' => $entity->id(),
      'langcode' => $entity->language()->getId(),
    ];

    // Try to delete the favorite...
    $query = $this->connection->delete(self::TABLE);
    foreach ($keys as $field => $value) {
      $query->condition($field, $value);
    }
    $isFavorite = !$query->execute();

    // ...and add the favorite when nothing was removed.
    if ($isFavorite) {
      $this->connection->merge(self::TABLE)
        ->keys($keys)
        ->fields(['created' => $this->time->getRequestTime()])
        ->execute();
    }

    // Clear the request cache and the user's rendered favorites.
    unset($this->favorites[$account->id() . ':' . $entity->getEntityTypeId()]);
    $this->cacheTagsInvalidator->invalidateTags(['helfi_favorite:' . $account->id()]);

    return $isFavorite;
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
