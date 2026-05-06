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
    public int $level,
    public string $text,
    public ?string $fragment,
  ) {
  }

  /**
   * Whether this heading refers to the same section as the given title+level.
   *
   * The DOM extractor and Markdown chunker can disagree slightly on heading
   * text (the chunker sees Markdown emphasis markers like *bold*, and
   * collapsed whitespace), so we compare normalized text.
   */
  public function matches(int $level, string $title): bool {
    return $this->level === $level
      && self::normalize($this->text) === self::normalize($title);
  }

  /**
   * Normalize heading text for comparison.
   */
  private static function normalize(string $text): string {
    $text = preg_replace('/[*_`]/u', '', $text) ?? $text;
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    return mb_strtolower(trim($text));
  }

}
