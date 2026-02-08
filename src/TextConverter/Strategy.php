<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\TextConverter;

/**
 * Text conversion strategy.
 */
enum Strategy: string {
  case Default = 'default';
  case Markdown = 'markdown';
}
