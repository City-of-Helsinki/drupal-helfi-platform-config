<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_hearings;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Lazy builder for hearings.
 */
final class LazyBuilder implements TrustedCallbackInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Lazy builder callback function.
   *
   * @return array
   *   The render array.
   */
  public function lazyBuild(): array {
    $build = [];
    $storage = $this->entityTypeManager
      ->getStorage('helfi_hearings');

    // Loading external entities makes network requests.
    $entities = $storage->loadMultiple();

    $cache = new CacheableMetadata();

    if (!$entities) {
      // Retries request every minute if no hearings are found.
      $cache->setCacheMaxAge(60);
    }

    foreach ($entities as $item) {
      // See 'persistent_cache_max_age' for the external entity type.
      $cache->addCacheableDependency($item);

      $build['list'][] = $this->entityTypeManager
        ->getViewBuilder('helfi_hearings')
        ->view($item);
    }

    $cache->applyTo($build);

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['lazyBuild'];
  }

}
