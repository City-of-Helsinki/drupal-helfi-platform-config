<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook implementations for helfi_search.
 */
final class ThemeHooks {

  /**
   * Implements hook_theme().
   *
   * @phpstan-return array<string, mixed>
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'helfi_search_similar_content' => [
        'variables' => [
          'edit_url' => NULL,
          'markdown' => '',
          'documents' => [],
        ],
        'template' => 'similar-content',
      ],
    ];
  }

}
