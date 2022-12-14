<?php

declare(strict_types = 1);

namespace Drupal\helfi_platform_config\DTO;

/**
 * Provides a DTO class to define enabled paragraph types.
 *
 * This is used to figure out what paragraph types should be enabled for each
 * paragraph reference field.
 *
 * For example: Lower content paragraph reference field in page node should
 * have 'hero', 'image' and 'accordion' paragraph types enabled.
 *
 * @code
 * function hook_enabled_paragraph_types() : array {
 *   return [
 *     new EnabledParagraphTypes('node', 'page', 'field_hero', 'hero'),
 *     new EnabledParagraphTypes('node', 'page', 'field_lower_content', 'image'),
 *     new EnabledParagraphTypes('node', 'page', 'field_lower_content', 'accordion'),
 *   ];
 * }
 * @endcode
 */
final class EnableParagraphTypes {

  /**
   * Constructs a new instance.
   *
   * @param string $entityType
   *   The entity type.
   * @param string $bundle
   *   The entity bundle.
   * @param string $field
   *   The entity reference field.
   * @param string $paragraphType
   *   The paragraph type to enable.
   */
  public function __construct(
    public string $entityType,
    public string $bundle,
    public string $field,
    public string $paragraphType,
  ) {
  }

}
