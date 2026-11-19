<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\EntityNotifications;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\helfi_platform_config\Translation\OutdatedTranslationChecker;

/**
 * Notifies about translations that are outdated compared to the original.
 *
 * The original translation gets the notification when any of its
 * translations is outdated, and an outdated translation gets it for itself.
 * Up-to-date translations get none.
 */
class OutdatedTranslationNotificationProvider implements EntityNotificationProviderInterface {

  use StringTranslationTrait;

  public function __construct(
    private readonly OutdatedTranslationChecker $checker,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function getNotifications(ContentEntityInterface $entity, RefinableCacheableDependencyInterface $cacheability): array {
    // The result only changes when the entity is saved.
    $cacheability->addCacheableDependency($entity);

    // Content translation adds the overview link template to the entity types
    // it supports.
    if (!$entity->isTranslatable() || !$entity->hasLinkTemplate('drupal:content-translation-overview')) {
      return [];
    }

    $outdated = $entity->isDefaultTranslation()
      ? $this->checker->getOutdatedTranslations($entity) !== []
      : $this->checker->getOutdatedDays($entity) !== NULL;

    if (!$outdated) {
      return [];
    }

    // Link to the translations overview in the current interface language.
    $entityTypeId = $entity->getEntityTypeId();
    $url = Url::fromRoute("entity.$entityTypeId.content_translation_overview", [
      $entityTypeId => $entity->id(),
    ], [
      'attributes' => [
        // Provides the alert icon and color from hdbt_admin.
        'class' => ['translation-state--outdated'],
      ],
    ]);

    $access = $url->access(return_as_object: TRUE);
    $cacheability->addCacheableDependency($access);

    if (!$access->isAllowed()) {
      return [];
    }

    return [
      new EntityNotification(
        $this->t('Check the language versions', [], ['context' => 'Outdated translation']),
        $url,
        icon: 'alert-circle-fill',
      ),
    ];
  }

}
