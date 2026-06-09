<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Pipeline;

/**
 * A Markdown/HTML heading: its text and depth (h1..h6).
 */
final readonly class Heading {

  /**
   * Normalized form of $title, used by matches().
   *
   * The DOM extractor and Markdown chunker can disagree slightly on heading
   * text (the chunker sees Markdown emphasis markers like *bold*, and
   * collapsed whitespace), so we strip both before comparing.
   */
  public string $normalized;

  public function __construct(
    public string $title,
    public int $level,
  ) {
    $this->normalized = self::normalize($title);
  }

  /**
   * Whether this heading refers to the same section as another.
   */
  public function matches(Heading $other): bool {
    return $this->level === $other->level && $this->normalized === $other->normalized;
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
