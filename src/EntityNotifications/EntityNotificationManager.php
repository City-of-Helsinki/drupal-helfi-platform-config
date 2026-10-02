<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\EntityNotifications;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Collects entity notifications.
 */
final readonly class EntityNotificationManager {

  /**
   * Constructs a new instance.
   *
   * @param iterable<\Drupal\helfi_platform_config\EntityNotifications\EntityNotificationProviderInterface> $providers
   *   The notification providers.
   */
  public function __construct(
    #[AutowireIterator(tag: 'helfi_platform_config.entity_notification_provider')]
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
   * @return list<\Drupal\helfi_platform_config\EntityNotifications\EntityNotification>
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
