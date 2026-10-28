<?php

declare(strict_types=1);

namespace Drupal\helfi_users;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\LanguageInterface;
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
   * Favorite entity IDs keyed by user ID and entity type.
   *
   * @var array<string, array<int|string, true>>
   */
  private array $favorites = [];

  public function __construct(
    private readonly Connection $connection,
    private readonly TimeInterface $time,
    private readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
  ) {}

  /**
   * Checks whether the entity is a favorite of the user.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return bool
   *   TRUE if the entity is a favorite.
   */
  public function isFavorite(AccountInterface $account, EntityInterface $entity): bool {
    $key = $account->id() . ':' . $entity->getEntityTypeId();

    // Load all favorites of the entity type once per request.
    if (!isset($this->favorites[$key])) {
      $ids = $this->connection->select(self::TABLE, 'f')
        ->fields('f', ['entity_id'])
        ->condition('uid', $account->id())
        ->condition('entity_type', $entity->getEntityTypeId())
        ->condition('langcode', LanguageInterface::LANGCODE_NOT_SPECIFIED)
        ->execute()
        ->fetchCol();
      $this->favorites[$key] = array_fill_keys($ids, TRUE);
    }

    return isset($this->favorites[$key][$entity->id()]);
  }

  /**
   * Adds or removes the entity from the user's favorites.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return bool
   *   TRUE if the entity is now a favorite.
   */
  public function toggle(AccountInterface $account, EntityInterface $entity): bool {
    $keys = [
      'uid' => $account->id(),
      'entity_type' => $entity->getEntityTypeId(),
      'entity_id' => $entity->id(),
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
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

}
