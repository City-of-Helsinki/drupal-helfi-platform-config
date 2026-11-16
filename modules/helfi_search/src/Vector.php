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

  /**
   * Shortens a vector to the given dimensions.
   *
   * The `text-embedding-3` models are trained so that the first values carry
   * the most meaning. Removing values from the end and re-normalizing the
   * result gives the same result as requesting fewer dimensions with the API
   * `dimensions` parameter.
   *
   * @param float[] $vector
   *   The embedding.
   * @param int $dimensions
   *   The target dimensions.
   *
   * @return float[]
   *   The shortened, normalized embedding.
   *
   * @see https://platform.openai.com/docs/guides/embeddings#use-cases
   */
  public static function truncate(array $vector, int $dimensions): array {
    if ($dimensions <= 0 || $dimensions >= count($vector)) {
      return $vector;
    }

    $vector = array_slice(array_values($vector), 0, $dimensions);
    $norm = array_map(static fn(float $value) => $value * $value, $vector)
        |> array_sum(...)
        |> sqrt(...);

    if ($norm == 0) {
      return $vector;
    }

    return array_map(static fn (float $value) => $value / $norm, $vector);
  }

}
