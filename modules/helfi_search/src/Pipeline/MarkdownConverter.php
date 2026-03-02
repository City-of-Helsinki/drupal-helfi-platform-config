<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Pipeline;

use League\HTMLToMarkdown\HtmlConverter;

/**
 * Converts cleaned HTML to Markdown while preserving semantic structure.
 *
 * Heading hierarchy (h1→#, h2→##, h3→###), lists, and emphasis are preserved.
 * Links and images should be handled before this stage.
 */
class MarkdownConverter {

  /**
   * Convert HTML to Markdown.
   *
   * @param string $html
   *   Cleaned HTML to convert.
   *
   * @return string
   *   Markdown text.
   */
  public function convert(string $html): string {
    if (empty($html)) {
      return '';
    }

    $converter = new HtmlConverter([
      'header_style' => 'atx',
      'strip_tags' => TRUE,
      'italic_style' => '*',
      'bold_style' => '**',
      'hard_break' => FALSE,
      'strip_placeholder_links' => TRUE,
    ]);

    return $converter->convert($html);
  }

}
