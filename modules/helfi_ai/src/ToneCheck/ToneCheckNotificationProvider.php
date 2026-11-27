<?php

declare(strict_types=1);

namespace Drupal\helfi_ai\ToneCheck;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\helfi_platform_config\EntityNotifications\EntityNotification;
use Drupal\helfi_platform_config\EntityNotifications\EntityNotificationProviderInterface;
use Drupal\node\NodeInterface;

/**
 * Links pages flagged for a tone check to their edit form.
 */
class ToneCheckNotificationProvider implements EntityNotificationProviderInterface {

  use StringTranslationTrait;

  public function __construct(
    private readonly ToneCheckPrecheck $precheck,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly AccountInterface $currentUser,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function getNotifications(ContentEntityInterface $entity, RefinableCacheableDependencyInterface $cacheability): array {
    $cacheability->addCacheableDependency($this->configFactory->get('helfi_ai.settings'));
    $cacheability->addCacheContexts(['user.permissions']);

    if (!$entity instanceof NodeInterface) {
      return [];
    }
    $cacheability->addCacheTags($this->precheck->getCacheTags($entity));

    if (!$this->currentUser->hasPermission('use helfi ai tone check') || !$this->precheck->isPending($entity)) {
      return [];
    }
    $url = $entity->toUrl('edit-form', ['query' => ['helfi_ai' => 'tone_check']]);
    $access = $url->access(return_as_object: TRUE);
    $cacheability->addCacheableDependency($access);

    if (!$access->isAllowed()) {
      return [];
    }
    return [
      new EntityNotification($this->t('Check tone', [], ['context' => 'Helfi AI']), $url),
    ];
  }

}
