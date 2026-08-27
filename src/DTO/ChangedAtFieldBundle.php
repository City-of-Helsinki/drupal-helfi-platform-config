<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\DTO;

/**
 * Provides a DTO class for the 'changed_at' field.
 *
 * This is used to figure out which entity type and bundle combinations
 * should get the reusable 'changed_at' field, populated with the time the
 * content was last changed via a form submission.
 *
 * @code
 * function hook_helfi_changed_at_field_bundles() : array {
 *   return [
 *     new ChangedAtFieldBundle('node', 'news_item'),
 *   ];
 * }
 * @endcode
 */
final class ChangedAtFieldBundle {

  /**
   * Constructs a new instance.
   *
   * @param string $entityType
   *   The entity type.
   * @param string $bundle
   *   The entity bundle.
   */
  public function __construct(
    public string $entityType,
    public string $bundle,
  ) {
  }

}
