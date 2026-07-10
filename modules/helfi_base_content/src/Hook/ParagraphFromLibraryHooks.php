<?php

declare(strict_types=1);

namespace Drupal\helfi_base_content\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\helfi_base_content\Entity\ParagraphFromLibrary;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Hooks for helfi_base_content module.
 */
class ParagraphFromLibraryHooks {

  use AutowireTrait;

  /**
   * Implements hook_ENTITY_TYPE_access().
   */
  #[Hook('paragraph_access')]
  public static function paragraphAccess(ParagraphInterface $entity, string $operation, AccountInterface $account): AccessResultInterface {
    if (
      $operation !== 'view' ||
      $account->isAuthenticated() ||
      !$entity instanceof ParagraphFromLibrary ||
      !$entity->isNotPublished()
    ) {
      return AccessResult::neutral();
    }

    $access = AccessResult::forbidden('Referenced library item is unpublished.')
      ->addCacheableDependency($entity)
      ->addCacheContexts(['user.roles:authenticated']);

    if ($libraryItem = $entity->get('field_reusable_paragraph')->entity) {
      $access->addCacheableDependency($libraryItem);
    }

    return $access;
  }

}
