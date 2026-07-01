<?php

declare(strict_types=1);

namespace Drupal\helfi_llms_txt\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Platform config hooks.
 */
final readonly class PlatformConfig {

  /**
   * Implements hook_platform_config_grant_permissions().
   *
   * @phpstan-return array<string, string[]>
   */
  #[Hook('platform_config_grant_permissions')]
  public function permissions(): array {
    return [
      'admin' => [
        'administer llms.txt',
      ],
    ];
  }

}
