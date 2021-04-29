<?php

namespace Drupal\helfi_platform_config;

/**
 * Sanitation handles removal of content.
 *
 * @package Drupal\helfi_platform_config
 */
class Sanitation {

  /**
   * Removes nodes of given content type.
   *
   * @param string $node_type
   *   Content type.
   */
  public static function removeContent(string $node_type): void {
    \Drupal::logger('node')->notice('Removing all content of type @type', ['@type' => $node_type]);
    $nids = \Drupal::entityQuery('node')
      ->condition('type', $node_type)
      ->accessCheck(FALSE)
      ->execute();
    $controller = \Drupal::entityTypeManager()->getStorage('node');
    $entities = $controller->loadMultiple($nids);
    $controller->delete($entities);
  }

}
