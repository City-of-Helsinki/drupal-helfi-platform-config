<?php

declare(strict_types=1);

namespace Drupal\helfi_users\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_users\Plugin\Derivative\FavoriteActionDeriver;

/**
 * Removes the selected entities from the user's favorites.
 */
#[Action(
  id: 'helfi_favorite_remove',
  label: new TranslatableMarkup('Remove from favourites'),
  deriver: FavoriteActionDeriver::class,
)]
final class RemoveFavorite extends FavoriteActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute(?EntityInterface $entity = NULL): TranslatableMarkup {
    if ($entity) {
      $this->favoriteStorage->remove($this->currentUser, $entity);
    }
    return $this->t('Remove from favourites');
  }

}
