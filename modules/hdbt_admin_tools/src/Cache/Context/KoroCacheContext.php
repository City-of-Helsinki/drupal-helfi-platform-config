<?php

declare(strict_types=1);

namespace Drupal\hdbt_admin_tools\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CacheContextInterface;
use Drupal\hdbt_admin_tools\Config\KoroPathOverride;

/**
 * Cache context for the koro of the current path.
 *
 * Cache context ID: 'koro'.
 */
final class KoroCacheContext implements CacheContextInterface {

  public function __construct(
    private readonly KoroPathOverride $koroPathOverride,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getLabel(): string {
    return (string) t('Koro');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext(): string {
    return $this->koroPathOverride->getKoro() ?? 'site';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata(): CacheableMetadata {
    return (new CacheableMetadata())
      ->addCacheTags(['config:' . KoroPathOverride::CONFIG_NAME]);
  }

}
