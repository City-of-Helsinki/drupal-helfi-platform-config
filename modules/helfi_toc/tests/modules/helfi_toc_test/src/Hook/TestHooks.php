<?php

declare(strict_types=1);

namespace Drupal\helfi_toc_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Test hooks for HELfi Table of contents.
 */
class TestHooks {

  /**
   * Implements hook_helfi_toc_forms_alter().
   *
   * @param array<int, string> $forms
   *   The whitelisted form ids.
   */
  #[Hook('helfi_toc_forms_alter')]
  public function helfiTocFormsAlter(array &$forms): void {
    $forms[] = 'custom_toc_form';
  }

}
