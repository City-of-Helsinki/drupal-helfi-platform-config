<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Pipeline;

/**
 * One heading found in the source DOM, paired with its URL fragment.
 *
 * Fragment is NULL when the heading is excluded from the table of contents
 * (e.g. it sits inside a .hide-from-table-of-contents wrapper).
 */
final readonly class HeadingFragment {

  public function __construct(
    public Heading $heading,
    public ?string $fragment,
  ) {
  }

}
