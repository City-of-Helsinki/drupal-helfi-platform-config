<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook implementations for platform config module.
 */
class ThemeHooks {

  /**
   * Implements hook_theme().
   *
   * @phpstan-return array<string, mixed>
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'hero_block' => [
        'variables' => [
          'title' => NULL,
          'paragraphs' => NULL,
          'is_revision' => NULL,
          'first_paragraph_grey' => NULL,
        ],
      ],
      'lower_content_block' => [
        'variables' => [
          'title' => NULL,
          'is_revision' => NULL,
          'paragraphs' => NULL,
          'computed' => NULL,
        ],
      ],
      'profile_block' => [
        'variables' => [
          'logged_in' => FALSE,
          'display_name' => NULL,
          'full_name' => NULL,
          'email' => NULL,
          'url' => NULL,
        ],
      ],
      'sidebar_content_block' => [
        'variables' => [
          'title' => NULL,
          'is_revision' => NULL,
          'paragraphs' => NULL,
          'computed' => NULL,
        ],
      ],
      'react_and_share' => [
        'variables' => [
          'title' => NULL,
          'content' => NULL,
        ],
      ],
      'helfi_entity_notifications' => [
        'variables' => [
          'notifications' => [],
        ],
      ],
      'helfi_location_autocomplete' => [
        'variables' => [
          'attributes' => [],
          'children' => NULL,
        ],
      ],
      'helfi_htmx_preview' => [
        'variables' => [
          'attributes' => [],
          'num_items' => NULL,
          'message' => NULL,
          'wrapper' => 'div',
        ],
      ],
    ];
  }

}
