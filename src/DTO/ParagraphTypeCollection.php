<?php

declare(strict_types = 1);

namespace Drupal\helfi_platform_config\DTO;

/**
 * Provides a DTO class to define paragraph types for given entity type/bundle.
 *
 * This is used to figure out what paragraph types should be enabled for each
 * entity type and bundle combinations.
 *
 * For example: Lower content paragraph reference field in page node should
 * have 'image' and 'accordion' paragraph types enabled, and field hero should
 * have 'hero' paragraph type enabled.
 *
 * @code
 * function hook_enabled_paragraph_types() : array {
 *   return [
 *     new ParagraphTypeCollection('node', 'page', 'field_hero', 'hero'),
 *     new ParagraphTypeCollection('node', 'page', 'field_lower_content', 'image'),
 *     new ParagraphTypeCollection('node', 'page', 'field_lower_content', 'accordion'),
 *   ];
 * }
 * @endcode
 */
final class ParagraphTypeCollection {

  /**
   * Constructs a new instance.
   *
   * @param string $entityType
   *   The entity type.
   * @param string $bundle
   *   The entity bundle.
   * @param string $field
   *   The entity reference field.
   * @param string $paragraph
   *   The paragraph type to enable.
   * @param int $weight
   *   The weight.
   */
  public function __construct(
    public string $entityType,
    public string $bundle,
    public string $field,
    public string $paragraph,
    public int $weight = 0,
  ) {
  }

}
