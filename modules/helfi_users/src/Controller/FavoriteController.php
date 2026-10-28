<?php

declare(strict_types=1);

namespace Drupal\helfi_users\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\helfi_users\FavoriteStorage;

/**
 * Toggles favorites.
 */
final readonly class FavoriteController implements ContainerInjectionInterface {

  use AutowireTrait;

  public function __construct(
    private FavoriteStorage $favoriteStorage,
    private AccountInterface $currentUser,
  ) {}

  /**
   * Checks access to toggle the favorite.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, EntityInterface $entity): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'use favorites')
      ->andIf($entity->access('update', $account, TRUE));
  }

  /**
   * Toggles the favorite and returns the updated button.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string $display
   *   The button display variant.
   *
   * @return array<string, mixed>
   *   The favorite toggle render array.
   */
  public function toggle(EntityInterface $entity, string $display): array {
    return [
      '#theme' => 'helfi_favorite_toggle',
      '#entity' => $entity,
      '#is_favorite' => $this->favoriteStorage->toggle($this->currentUser, $entity),
      '#show_label' => $display === 'label',
    ];
  }

}
