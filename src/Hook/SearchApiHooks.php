<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\helfi_platform_config\SearchAPI\RestrictedElasticSearchBackend;
use Drupal\helfi_platform_config\SearchAPI\SharedIndexRebuildDetector;

/**
 * Search API hooks.
 */
final class SearchApiHooks {

  public function __construct(
    private readonly SharedIndexRebuildDetector $sharedIndexRebuildDetector,
  ) {
  }

  /**
   * Implements hook_search_api_backend_info_alter().
   *
   * Guards shared indexes on the Elasticsearch backend.
   *
   * @param array<string, array<string, mixed>> $backendInfo
   *   The backend plugin definitions, keyed by plugin id.
   */
  #[Hook('search_api_backend_info_alter')]
  public function backendInfoAlter(array &$backendInfo): void {
    if (isset($backendInfo['elasticsearch'])) {
      $backendInfo['elasticsearch']['class'] = RestrictedElasticSearchBackend::class;
    }
  }

  /**
   * Implements hook_cron().
   *
   * Reindexes shared indexes that their main instance cleared or rebuilt.
   */
  #[Hook('cron')]
  public function cron(): void {
    $this->sharedIndexRebuildDetector->reindexRecreatedIndexes();
  }

}
