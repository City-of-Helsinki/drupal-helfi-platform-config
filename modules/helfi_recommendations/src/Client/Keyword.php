<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations\Client;

/**
 * DTO for suggested keyword.
 */
final class Keyword {

  /**
   * Constructs a new instance.
   *
   * @param string $label
   *   Translated keyword label.
   * @param float $score
   *   Keyword score.
   * @param string $uri
   *   Keyword uri. Use this as a unique id.
   */
  public function __construct(
    public readonly string $label,
    public readonly float $score,
    public readonly string $uri,
  ) {
  }

}
