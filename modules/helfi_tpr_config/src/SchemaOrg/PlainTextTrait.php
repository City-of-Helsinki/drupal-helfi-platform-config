<?php

declare(strict_types=1);

namespace Drupal\helfi_tpr_config\SchemaOrg;

/**
 * Helpers for emitting plain-text schema.org values.
 */
trait PlainTextTrait {

  /**
   * Strips markup and surrounding whitespace from a text value.
   *
   * @param string|null $text
   *   The raw text value.
   *
   * @return string|null
   *   The plain-text value, or NULL when empty.
   */
  protected function cleanText(?string $text): ?string {
    if ($text === NULL) {
      return NULL;
    }
    $text = trim(strip_tags($text));
    return $text === '' ? NULL : $text;
  }

}
