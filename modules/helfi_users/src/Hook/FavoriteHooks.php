<?php

declare(strict_types=1);

namespace Drupal\helfi_users\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Favorite hooks.
 */
final readonly class FavoriteHooks {

  /**
   * Implements hook_theme().
   *
   * @return array<string, mixed>
   *   The theme definitions.
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'helfi_favorite_toggle' => [
        'variables' => [
          'attributes' => [],
          'is_favorite' => FALSE,
          'show_label' => FALSE,
        ],
      ],
    ];
  }

}
