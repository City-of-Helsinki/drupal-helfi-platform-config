<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\DTO;

use Dom\Element;

/**
 * Describes what one heading's anchor id should become.
 *
 * @see \Drupal\helfi_platform_config\HeadingIdInjector
 *
 * @internal
 */
final readonly class HeadingRecord {

  /**
   * Constructs a new instance.
   *
   * @param string $tag
   *   The lower-cased tag name, such as 'h2'.
   * @param bool $hasId
   *   Whether the heading already has an id attribute.
   * @param string|null $newId
   *   The id to inject, or NULL when the heading keeps whatever it has.
   * @param bool $excluded
   *   Whether the heading must be left untouched.
   */
  public function __construct(
    public string $tag,
    public bool $hasId,
    public ?string $newId,
    public bool $excluded,
  ) {
  }

  /**
   * Checks whether this record was planned for the given heading.
   *
   * @return bool
   *   TRUE when the record belongs to the heading.
   */
  public function describes(Element $element): bool {
    return $this->tag === $element->localName
      && $this->hasId === $element->hasAttribute('id');
  }

}
