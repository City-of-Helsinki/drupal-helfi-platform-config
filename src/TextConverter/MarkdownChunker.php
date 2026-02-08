<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\TextConverter;

/**
 * Splits markdown text into chunks based on heading structure.
 */
class MarkdownChunker {

  /**
   * Split markdown into chunks by heading level.
   *
   * Each chunk includes context headers (headers above the split level)
   * and optional additional context lines prepended to every chunk.
   *
   * @param string $markdown
   *   The markdown text to split.
   * @param int $headerLevel
   *   The heading level to split on (e.g. 2 for ##).
   * @param string[] $context
   *   Additional context lines to prepend to every chunk.
   *
   * @return string[]
   *   Array of markdown chunks.
   */
  public function chunk(string $markdown, int $headerLevel = 2, array $context = []): array {
    if ($markdown === '') {
      return [];
    }

    $lines = explode("\n", $markdown);
    $headerPattern = '/^(#{1,' . $headerLevel . '})\s/';

    // Track context headers (headers above the split level).
    $contextHeaders = [];
    // Context headers snapshot at the start of the current chunk.
    $chunkContextHeaders = [];
    // Collect raw chunks: each chunk is an array of lines.
    $chunks = [];
    $currentLines = [];

    foreach ($lines as $line) {
      if (preg_match($headerPattern, $line, $matches)) {
        $level = strlen($matches[1]);

        if ($level === $headerLevel) {
          // Split-level header: flush the current chunk and start new one.
          // For the intro chunk (first), include context headers in the body.
          if (empty($chunks)) {
            ksort($contextHeaders);
            $introLines = array_merge(array_values($contextHeaders), $currentLines);
            $chunks[] = [
              'lines' => $introLines,
              'contextHeaders' => [],
            ];
          }
          else {
            $chunks[] = [
              'lines' => $currentLines,
              'contextHeaders' => $chunkContextHeaders,
            ];
          }
          // Snapshot current context for the new chunk.
          $chunkContextHeaders = $contextHeaders;
          $currentLines = [$line];
          continue;
        }

        // Context header (above split level): update tracking.
        $contextHeaders[$level] = $line;
        // Clear any deeper context headers (between this level and split).
        foreach (array_keys($contextHeaders) as $key) {
          if ($key > $level && $key < $headerLevel) {
            unset($contextHeaders[$key]);
          }
        }
        // Context headers are prepended during assembly, not added to lines.
        continue;
      }

      $currentLines[] = $line;
    }

    // Flush the last chunk.
    $chunks[] = [
      'lines' => $currentLines,
      'contextHeaders' => $chunkContextHeaders,
    ];

    // If no split-level headers were found, return as single chunk.
    if (count($chunks) === 1) {
      $text = trim($markdown);
      if (!empty($context)) {
        $text = implode("\n", $context) . "\n" . $text;
      }
      return [$text];
    }

    $contextPrefix = !empty($context)
      ? implode("\n", $context) . "\n"
      : '';

    $result = [];
    foreach ($chunks as $i => $chunk) {
      $body = implode("\n", $chunk['lines']);

      // Skip empty intro chunks (no content before first split header).
      if ($i === 0 && trim($body) === '' && empty($context)) {
        continue;
      }

      // Prepend context headers.
      $headerPrefix = '';
      if (!empty($chunk['contextHeaders'])) {
        ksort($chunk['contextHeaders']);
        $headerPrefix = implode("\n", $chunk['contextHeaders']) . "\n";
      }

      $assembled = $contextPrefix . $headerPrefix . $body;
      $result[] = trim($assembled);
    }

    return $result;
  }

}
