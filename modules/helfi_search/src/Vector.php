<?php

declare(strict_types=1);

namespace Drupal\helfi_search;

/**
 * Converts embedding vectors to and from their stored format.
 */
final class Vector {

  /**
   * Packs a vector for storage.
   *
   * @param float[] $vector
   *   The embedding.
   *
   * @return string
   *   The packed embedding.
   */
  public static function pack(array $vector): string {
    return pack('g*', ...array_map('floatval', $vector));
  }

  /**
   * Unpacks a stored vector.
   *
   * @param string $packed
   *   The packed embedding.
   *
   * @return float[]
   *   The embedding vector.
   */
  public static function unpack(string $packed): array {
    return array_values(unpack('g*', $packed) ?: []);
  }

}
