<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Pipeline;

/**
 * Reduces a chunk's markdown body to a plain-text snippet.
 *
 * @internal
 *   Tightly coupled to the output of MarkdownConverter. Do not pass
 *   arbitary Markdown to this.
 */
final class SnippetRenderer {

  /**
   * Render chunk text as a truncated plain-text snippet.
   */
  public static function render(string $markdown): string {
    $text = self::stripMarkdown($markdown);
    $text = html_entity_decode(strip_tags($text), ENT_HTML5, 'UTF-8');
    $text = trim((string) preg_replace('/\s+/u', ' ', $text));

    return $text;
  }

  /**
   * Render a text fragment from markdown.
   *
   * Text fragment allows linking and highlighting a specific part of the text.
   */
  public static function renderTextFragment(string $markdown): string {
    $text = self::stripMarkdown($markdown);
    $text = html_entity_decode(strip_tags($text), ENT_HTML5, 'UTF-8');
    $lines = explode("\n", $text);
    $textLines = [];

    // Create a text fragment from all lines that have at least 3 words.
    // If the line has less than 6 words, use the entire line for an exact
    // match. Else, use the first 3 and last 3 words to mark the start and end
    // of the fragment.
    foreach ($lines as $line) {
      $words = explode(' ', html_entity_decode($line));
      if (count($words) < 3) {
        continue;
      }

      if (count($words) < 6) {
        $text = implode(' ', $words);
        $textLines[] = sprintf('text=%s', self::percentEncode($text));
      }
      else {
        $start = implode(' ', array_slice($words, 0, 3));
        $end = implode(' ', array_slice($words, -3));

        $textLines[] = sprintf('text=%s,%s', self::percentEncode($start), self::percentEncode($end));
      }
    }

    return sprintf(':~:%s', implode('&', $textLines));
  }

  /**
   * Percent encode a string according to text matching spec.
   *
   * In addition to rawurlencode(), we also replace '-'.
   *
   * @see https://wicg.github.io/scroll-to-text-fragment/#syntax
   */
  public static function percentEncode(string $text): string {
    $text = rawurlencode($text);
    return str_replace('-', '%2D', $text);
  }

  /**
   * Strip Markdown formatting from a string.
   */
  private static function stripMarkdown(string $markdown): string {
    // 1. Strip top-level Markdown heading lines entirely. The result card
    // already carries a heading.
    // e.g. "# Heading\nStuff" -> "Stuff".
    $markdown = (string) preg_replace('/^#{1,6}\s+[^\n]*\R*/um', '', $markdown);

    // 2. Fenced code blocks: drop the opening and closing fence lines, keep
    // the body.
    // e.g. "```js\ncode()\n```" -> "code()\n".
    $markdown = (string) preg_replace('/^```[^\n]*$\R?/m', '', $markdown);

    // 3. Inline code spans: keep the inner text, drop balanced backtick runs.
    // e.g. "use `foo()` here" -> "use foo() here".
    $markdown = (string) preg_replace('/(`+)([^`]+?)\1/', '$2', $markdown);

    // 4. Horizontal rules.
    // e.g. "---" -> "".
    $markdown = (string) preg_replace('/^---$/m', '', $markdown);

    // 5. Emphasis. Inner content is bounded to non-marker, non-space, non-
    // backslash characters so we don't eat lone operators ("5 * 3") or
    // escaped delimiters ("\*literal\*").
    // e.g. "**bold**" -> "bold".
    $markdown = (string) preg_replace('/(?<!\\\\)\*\*([^*\s](?:.*?[^*\s])?)\*\*/', '$1', $markdown);
    // e.g. "*italic*" -> "italic".
    $markdown = (string) preg_replace('/(?<![*\w\\\\])\*([^*\s\\\\](?:.*?[^*\s\\\\])?)\*(?![*\w])/', '$1', $markdown);

    // 6. List, blockquote, and heading markers, looped so nested forms like
    // ">> deep", "> - item", or "- # heading" peel one layer per iteration.
    // e.g. "- a" -> "a", "1. a" -> "a", "  - sub" -> "sub",
    // "- # heading" -> "heading".
    do {
      $previous = $markdown;
      $markdown = (string) preg_replace('/^[ \t]*(?:-|\d+\.)[ \t]+/m', '', $markdown);
      $markdown = (string) preg_replace('/^[ \t]*>[ \t]?/m', '', $markdown);
      $markdown = (string) preg_replace('/^#{1,6}[ \t]+/m', '', $markdown);
    } while ($markdown !== $previous);

    // 7. Backslash escapes: turn \X back into X for markdown punctuation.
    // e.g. "\*literal\*" -> "*literal*", "\\back" -> "\back".
    $markdown = (string) preg_replace('/\\\\([\\\\`*_{}\[\]()#+\-.!~|>])/', '$1', $markdown);

    return $markdown;
  }

}
