<?php

declare(strict_types=1);

namespace Drupal\helfi_users\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_users\Plugin\Derivative\FavoriteActionDeriver;

/**
 * Adds the selected entities to the user's favorites.
 */
#[Action(
  id: 'helfi_favorite_add',
  label: new TranslatableMarkup('Add to favourites'),
  deriver: FavoriteActionDeriver::class,
)]
final class AddFavorite extends FavoriteActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute(?EntityInterface $entity = NULL): TranslatableMarkup {
    if ($entity) {
      $this->favoriteStorage->add($this->currentUser, $entity);
    }
    return $this->t('Add to favourites');
  }

}
