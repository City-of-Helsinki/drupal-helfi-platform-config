<?php

declare(strict_types=1);

namespace Drupal\helfi_users\Controller;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\helfi_users\FavoriteStorage;
use Drupal\helfi_users\FavoriteToggleBuilder;

/**
 * Toggles favorites.
 */
final readonly class FavoriteController implements ContainerInjectionInterface {

  use AutowireTrait;

  public function __construct(
    private FavoriteStorage $favoriteStorage,
    private FavoriteToggleBuilder $favoriteToggleBuilder,
    private AccountInterface $currentUser,
  ) {}

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
    $this->favoriteStorage->toggle($this->currentUser, $entity);
    return $this->favoriteToggleBuilder->build($entity, $display);
  }

}
