<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\helfi_platform_config\SearchAPI\RestrictedElasticSearchBackend;

/**
 * Search API hooks.
 */
final class SearchApiHooks {

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

}
