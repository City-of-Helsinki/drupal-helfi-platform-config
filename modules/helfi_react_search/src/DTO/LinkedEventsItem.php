<?php

declare(strict_types=1);

namespace Drupal\helfi_react_search\DTO;

/**
 * DTO for event list keyword filter.
 */
final readonly class LinkedEventsItem {

  /**
   * Constructs a new instance.
   *
   * @param string $id
   *   Keyword term id.
   * @param array<string, string> $name
   *   Keyword label translations keyed by langcode.
   */
  public function __construct(
    public string $id,
    public array $name,
  ) {
  }

}
