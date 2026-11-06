<?php

declare(strict_types=1);

namespace Drupal\helfi_users\Controller;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
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
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param string $langcode
   *   The translation language code.
   * @param string $display
   *   The button display variant.
   *
   * @return array<string, mixed>
   *   The favorite toggle render array.
   */
  public function toggle(ContentEntityInterface $entity, string $langcode, string $display): array {
    $translation = $entity->getTranslation($langcode);
    $this->favoriteStorage->toggle($this->currentUser, $translation);
    return $this->favoriteToggleBuilder->build($translation, $display);
  }

}
