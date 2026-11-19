<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\EntityNotifications;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Lazy builder for entity notifications.
 */
final readonly class EntityNotificationLazyBuilder implements TrustedCallbackInterface {

  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
    private EntityNotificationManager $notificationManager,
  ) {
  }

  /**
   * Builds a lazy placeholder for the entity notifications.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return array<string, mixed>
   *   The render array.
   */
  public static function lazyBuild(ContentEntityInterface $entity): array {
    // Placeholder arguments must be scalars.
    $arguments = [$entity->getEntityTypeId(), (string) $entity->id(), $entity->language()->getId()];

    return [
      '#lazy_builder' => [self::class . ':build', $arguments],
      '#create_placeholder' => TRUE,
      '#lazy_builder_preview' => ['#markup' => ''],
      '#cache' => [
        'keys' => ['helfi_entity_notifications', ...$arguments],
      ],
    ];
  }

  /**
   * Lazy builder callback for entity notifications.
   *
   * @param string $entityTypeId
   *   The entity type ID.
   * @param string $id
   *   The entity ID.
   * @param string $langcode
   *   The entity language.
   *
   * @return array<string, mixed>
   *   The render array.
   */
  public function build(string $entityTypeId, string $id, string $langcode): array {
    $entity = $this->entityTypeManager->getStorage($entityTypeId)->load($id);

    if (!$entity instanceof ContentEntityInterface) {
      return [];
    }

    if ($entity->hasTranslation($langcode)) {
      $entity = $entity->getTranslation($langcode);
    }

    $cacheability = new CacheableMetadata();
    $notifications = $this->notificationManager->getNotifications($entity, $cacheability);

    $build = [];
    if ($notifications) {
      $build = [
        '#theme' => 'helfi_entity_notifications',
        '#notifications' => $notifications,
        '#attached' => ['library' => ['helfi_platform_config/entity_notifications']],
      ];
    }

    $cacheability->applyTo($build);

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['build'];
  }

}
