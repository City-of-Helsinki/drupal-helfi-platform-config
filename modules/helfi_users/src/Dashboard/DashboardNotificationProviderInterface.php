<?php

declare(strict_types=1);

namespace Drupal\helfi_users\Dashboard;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides notifications for content entities listed in the user dashboard.
 *
 * Services implementing this interface must be tagged with
 * 'helfi_users.dashboard_notification_provider'.
 */
interface DashboardNotificationProviderInterface {

  /**
   * Gets notifications for the given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface $cacheability
   *   The cacheability of the notifications.
   *
   * @return list<\Drupal\helfi_users\Dashboard\DashboardNotification>
   *   The notifications.
   */
  public function getNotifications(ContentEntityInterface $entity, RefinableCacheableDependencyInterface $cacheability): array;

}
