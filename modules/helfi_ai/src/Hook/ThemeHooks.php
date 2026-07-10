<?php

declare(strict_types=1);

namespace Drupal\helfi_ai\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook implementations for HELfi AI.
 */
class ThemeHooks {

  /**
   * Implements hook_theme().
   *
   * @return array<string, mixed>
   *   The theme definitions.
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'ai_title_suggestions' => [
        'variables' => ['suggestions' => []],
        'template' => 'ai-title-suggestions',
      ],
      'ai_message' => [
        'variables' => ['text' => NULL],
        'template' => 'ai-message',
      ],
    ];
  }

}
