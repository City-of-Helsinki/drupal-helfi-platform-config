<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Pipeline;

/**
 * Splits normalized Markdown content into semantically coherent chunks.
 *
 * Short content (< 400 estimated tokens) is returned as-is. Longer content is
 * first split on Markdown headings, then oversized sections are recursively
 * split on paragraph boundaries, sentence boundaries, and finally word
 * boundaries.
 */
class ContentChunker {

  /**
   * Target chunk size in characters (≈ 512 tokens × 4 chars/token).
   */
  private const int DEFAULT_CHUNK_SIZE = 2048;

  /**
   * Overlap in characters (≈ 64 tokens × 4 chars/token).
   */
  private const int DEFAULT_OVERLAP = 256;

  /**
   * Estimated token count below which content is not chunked.
   */
  private const int SHORT_CONTENT_TOKEN_THRESHOLD = 400;

  /**
   * Chunk markdown content into embedding-ready pieces.
   *
   * @param string $markdown
   *   Normalized Markdown text.
   *
   * @return Chunk[]
   *   Array of chunks.
   */
  public function chunk(string $markdown): array {
    if (empty($markdown)) {
      return [new Chunk('')];
    }

    // Short content: skip chunking entirely.
    if (strlen($markdown) / 4 < self::SHORT_CONTENT_TOKEN_THRESHOLD) {
      return [new Chunk($markdown)];
    }

    $chunks = [];

    // Chunk stack keyed by level for hierarchy tracking.
    $stack = [];

    foreach ($this->splitByHeadings($markdown) as $section) {
      [$title, $level, $body] = $section;

      // Remove items at same or deeper level.
      $stack = array_filter($stack, fn (int $key) => $key < $level, ARRAY_FILTER_USE_KEY);

      // The section's own heading is the last context entry (if any).
      $parent = array_last($stack);

      foreach ($this->recursiveSplit($body) as $subText) {
        // Add current title to each chunk.
        $text = $title ? sprintf("%s %s\n%s", str_repeat('#', $level), $title, $subText) : $subText;

        $stack[$level] = $chunks[] = new Chunk(trim($text), $parent, [
          'title' => $title,
          'level' => $level,
        ]);
      }
    }

    return $chunks ?: [new Chunk($markdown)];
  }

  /**
   * Split Markdown into sections based on h1/h2/h3 headings.
   *
   * @return array{0: string|null, 1: int, 2: string}[]
   *   Array tuples.
   */
  private function splitByHeadings(string $markdown): array {
    // Split and capture the delimiter groups: hash chars and heading text.
    $parts = preg_split(
      '/^(#{1,3})\s+(.+)\n$/m',
      $markdown,
      flags: PREG_SPLIT_DELIM_CAPTURE,
    );

    if ($parts === FALSE) {
      throw new \InvalidArgumentException('Invalid markdown string');
    }

    $sections = [];

    // Current heading level.
    $level = 0;

    $intro = trim($parts[0]);
    if (!empty($intro)) {
      $sections[] = [NULL, $level, $intro];
    }

    // Parts come in groups of 3 starting at index 1: [hashes, title, body].
    // Index 0 is intro content before the first heading.
    $i = 1;
    while ($i < count($parts)) {
      $hashes = $parts[$i] ?? '';
      $title = $parts[$i + 1] ?? '';
      $body = $parts[$i + 2] ?? '';

      $level = strlen($hashes);

      $sections[] = [$title, $level, $body];

      $i += 3;
    }

    return $sections;
  }

  /**
   * Recursively split text by progressively finer boundaries.
   *
   * Tries separators in order: paragraph break → sentence end → word boundary.
   * Adds overlap between consecutive chunks for context continuity.
   *
   * @param string $text
   *   Text to split.
   * @param string[] $separators
   *   Separators tried in order of preference.
   *
   * @return string[]
   *   Array of text chunks.
   */
  private function recursiveSplit(
    string $text,
    array $separators = ["\n\n", ". ", " "],
  ): array {
    if (strlen($text) <= self::DEFAULT_CHUNK_SIZE) {
      return [$text];
    }

    if (empty($separators)) {
      // No separators left: hard-split at DEFAULT_CHUNK_SIZE with overlap.
      $chunks = [];
      $offset = 0;
      $length = strlen($text);
      while ($offset < $length) {
        $chunks[] = substr($text, $offset, self::DEFAULT_CHUNK_SIZE);
        $offset += max(1, self::DEFAULT_CHUNK_SIZE - self::DEFAULT_OVERLAP);
      }
      return $chunks;
    }

    $separator = array_shift($separators);
    $parts = explode($separator, $text);

    if (count($parts) <= 1) {
      // Separator not found — try the next one.
      return $this->recursiveSplit($text, $separators);
    }

    $chunks = [];
    $currentParts = [];
    $currentLen = 0;

    foreach ($parts as $part) {
      $addLen = ($currentLen > 0 ? strlen($separator) : 0) + strlen($part);

      if ($currentLen + $addLen <= self::DEFAULT_CHUNK_SIZE) {
        $currentParts[] = $part;
        $currentLen += $addLen;
      }
      else {
        if ($currentParts !== []) {
          $chunks[] = implode($separator, $currentParts);

          // Overlap: carry the last part from previous chunk for continuity.
          $lastPart = array_last($currentParts);
          $currentParts = [$lastPart, $part];
          $currentLen = strlen($lastPart) + strlen($separator) + strlen($part);
        }
        else {
          // Single part is already too large — recurse into it.
          foreach ($this->recursiveSplit($part, $separators) as $sub) {
            $chunks[] = $sub;
          }
        }
      }
    }

    if ($currentParts !== []) {
      $chunks[] = implode($separator, $currentParts);
    }

    return $chunks;
  }

}
