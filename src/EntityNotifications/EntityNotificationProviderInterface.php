<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\EntityNotifications;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides notifications for content entities.
 *
 * Services implementing this interface must be tagged with
 * 'helfi_platform_config.entity_notification_provider'.
 */
interface EntityNotificationProviderInterface {

  /**
   * Gets notifications for the given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface $cacheability
   *   The cacheability of the notifications.
   *
   * @return list<\Drupal\helfi_platform_config\EntityNotifications\EntityNotification>
   *   The notifications.
   */
  public function getNotifications(ContentEntityInterface $entity, RefinableCacheableDependencyInterface $cacheability): array;

}
