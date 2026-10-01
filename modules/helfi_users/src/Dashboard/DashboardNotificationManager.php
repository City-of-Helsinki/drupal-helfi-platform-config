<?php

declare(strict_types=1);

namespace Drupal\helfi_users\Dashboard;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Collects dashboard notifications.
 */
final readonly class DashboardNotificationManager {

  /**
   * Constructs a new instance.
   *
   * @param iterable<\Drupal\helfi_users\Dashboard\DashboardNotificationProviderInterface> $providers
   *   The notification providers.
   */
  public function __construct(
    #[AutowireIterator(tag: 'helfi_users.dashboard_notification_provider')]
    private iterable $providers,
  ) {}

  /**
   * Gets notifications for the given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface $cacheability
   *   Collects the cacheability of all providers.
   *
   * @return list<\Drupal\helfi_users\Dashboard\DashboardNotification>
   *   The notifications.
   */
  public function getNotifications(ContentEntityInterface $entity, RefinableCacheableDependencyInterface $cacheability): array {
    $notifications = [];
    foreach ($this->providers as $provider) {
      array_push($notifications, ...$provider->getNotifications($entity, $cacheability));
    }
    return $notifications;
  }

}
