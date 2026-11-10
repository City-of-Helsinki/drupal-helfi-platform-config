<?php

declare(strict_types=1);

namespace Drupal\helfi_node_survey\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\helfi_node_survey\Entity\Survey;

/**
 * Entity hook implementations.
 */
class EntityHooks {

  /**
   * Implements hook_entity_bundle_info_alter().
   *
   * @param array<string, array<string, array<string, mixed>>> $bundles
   *   The bundle info, keyed by entity type and bundle.
   */
  #[Hook('entity_bundle_info_alter')]
  public function entityBundleInfoAlter(array &$bundles): void {
    if (isset($bundles['node']['survey'])) {
      $bundles['node']['survey']['class'] = Survey::class;
    }
  }

}
