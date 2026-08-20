<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config;

use Dom\Element;
use Dom\HTMLDocument;
use Drupal\helfi_platform_config\DTO\HeadingRecord;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Injects anchor ids into the headings of a rendered page.
 *
 * Only the heading open tags are rewritten. Everything else comes back
 * unmodified. We avoid re-serializing the document so that big-pipe
 * substitutions don't break.
 *
 * @see \Drupal\helfi_platform_config\EventSubscriber\HeadingIdResponseSubscriber
 * @see \Drupal\helfi_platform_config\HeadingSlugger
 */
final class HeadingIdInjector implements LoggerAwareInterface {

  use LoggerAwareTrait;

  /**
   * The class marking the main content wrapper.
   */
  private const string MAIN_CLASS = 'layout-main-wrapper';

  /**
   * Headings under an element with this class are left alone.
   */
  private const string EXCLUDE_CLASS = 'hide-from-table-of-contents';

  /**
   * Tags whose trees the parse should skip.
   */
  private const array HIDDEN_SUBTREES = ['noscript'];

  /**
   * Marks an id as generated here, so the JS fallback can verify it.
   *
   * @todo Remove along with the headingIdInjector.js fallback.
   */
  private const string GENERATED_ATTRIBUTE = 'data-helfi-heading-id';

  /**
   * Injects ids and tabindex into the headings inside the main wrapper.
   *
   * @param string $html
   *   The rendered page.
   * @param string $langcode
   *   Language code used for transliteration.
   *
   * @return string
   *   The page, with heading tags rewritten. Returned if anything looks off.
   */
  public function inject(string $html, string $langcode): string {
    // Bail before parsing anything if response has no headings.
    if (!str_contains($html, self::MAIN_CLASS) || !preg_match('/<h[2-6][\s>]/i', $html)) {
      return $html;
    }

    $start = $this->findMainStart($html);
    if ($start === NULL) {
      return $html;
    }

    $plan = $this->plan($html, $langcode);
    if (!$plan) {
      return $html;
    }

    $patched = $this->applyPlan(substr($html, $start), $plan);

    if ($patched === NULL) {
      // The source tag sequence did not match the parsed one.
      $this->logger?->error('Heading id injection failed: failed to parse the document');
      return $html;
    }

    return substr($html, 0, $start) . $patched;
  }

  /**
   * Calculate ids for headings.
   *
   * @return \Drupal\helfi_platform_config\DTO\HeadingRecord[]|null
   *   One record per heading, in document order. NULL when there is nothing
   *   to inject.
   */
  private function plan(string $html, string $langcode): ?array {
    try {
      $document = HTMLDocument::createFromString($html, LIBXML_NOERROR, 'UTF-8');
    }
    catch (\Throwable) {
      return NULL;
    }

    foreach (iterator_to_array($document->querySelectorAll(implode(', ', self::HIDDEN_SUBTREES))) as $node) {
      $node->parentNode?->removeChild($node);
    }

    $main = $document->querySelector(sprintf('main.%s', self::MAIN_CLASS));
    if (!$main) {
      return NULL;
    }

    // Ensure generated IDs do not collide with existing values.
    $reserved = [];
    foreach ($document->querySelectorAll('[id]') as $element) {
      if ($element instanceof Element) {
        $reserved[] = $element->getAttribute('id');
      }
    }

    $slugger = new HeadingSlugger($langcode, $reserved);

    $headings = $main->querySelectorAll('h2, h3, h4, h5, h6');
    if ($headings->length === 0) {
      return NULL;
    }

    // Every heading gets a record.
    $plan = [];
    foreach ($headings as $heading) {
      if (!$heading instanceof Element) {
        continue;
      }

      $hasId = $heading->hasAttribute('id');
      $excluded = $heading->parentElement?->closest('.' . self::EXCLUDE_CLASS) !== NULL;

      $plan[] = new HeadingRecord(
        tag: $heading->localName,
        hasId: $hasId,
        newId: $excluded || $hasId
          ? NULL
          : ($slugger->slug($heading->textContent) ?: NULL),
        excluded: $excluded,
      );
    }

    return $plan ?: NULL;
  }

  /**
   * Rewrites the heading open tags.
   *
   * @phpstan-param \Drupal\helfi_platform_config\DTO\HeadingRecord[] $plan
   *
   * @return string|null
   *   The rewritten content, or NULL when the source disagrees with the plan.
   */
  private function applyPlan(string $tail, array $plan): ?string {
    // Regex for finding heading open tags in HTML. The regex contains
    // two branches:
    // - skip group: a literal '<h2' inside a script, comment, <noscript>
    //   etc. must not be touched.
    // - heading branch: matches heading open tags with optional attributes,
    //   e.g. <h2 class="foobar">.
    $pattern = '#(?<skip><script\b[^>]*>.*?</script>'
      . '|<style\b[^>]*>.*?</style>'
      . '|<textarea\b[^>]*>.*?</textarea>'
      . '|<noscript\b[^>]*>.*?</noscript>'
      . '|<template\b[^>]*>.*?</template>'
      . '|<!--.*?-->)'
      . '|<h[2-6](?:\s(?:[^>"\']|"[^"]*"|\'[^\']*\')*)?>#is';

    $count = count($plan);
    $index = 0;
    $failed = FALSE;

    $patched = preg_replace_callback($pattern, function (array $match) use ($plan, $count, &$index, &$failed): string {
      // Everything past the last planned heading is outside the main wrapper.
      if ($failed || $index === $count || ($match['skip'] ?? '') !== '') {
        return $match[0];
      }

      // Only heading open tags reach this point.
      $record = $plan[$index] ?? NULL;
      $index++;

      $element = $this->parseTag($match[0]);

      // Validate that the plan still describes this document.
      if (!$element || !$record?->describes($element)) {
        $failed = TRUE;
        return $match[0];
      }

      if ($record->excluded) {
        return $match[0];
      }

      if ($record->newId !== NULL) {
        $element->setAttribute('id', $record->newId);
        $element->setAttribute(self::GENERATED_ATTRIBUTE, '');
      }
      // Make the heading focusable without adding it to the tab order, so
      // screen readers announce it.
      if (!$element->hasAttribute('tabindex')) {
        $element->setAttribute('tabindex', '-1');
      }

      // Serialize the $element.
      $tag = $element->ownerDocument->saveHtml($element);
      $end = sprintf('</%s>', $element->localName);

      if (!str_ends_with($tag, $end)) {
        $failed = TRUE;
        return $match[0];
      }

      // HTML5 parsing rules adds closing tag to open tags that we must strip.
      return substr($tag, 0, -strlen($end));
    }, $tail);

    if ($failed || $patched === NULL || $index !== $count) {
      return NULL;
    }

    return $patched;
  }

  /**
   * Gets the byte offset where the main wrapper's contents begin.
   *
   * @return int|null
   *   Offset of the first byte after the <main> open tag.
   */
  private function findMainStart(string $html): ?int {
    $offset = 0;

    while (preg_match('/<main\b[^>]*>/i', $html, $match, PREG_OFFSET_CAPTURE, $offset)) {
      $tag = $match[0][0];
      $offset = $match[0][1] + strlen($tag);

      if ($this->parseTag($tag)?->classList->contains(self::MAIN_CLASS)) {
        return $offset;
      }
    }

    return NULL;
  }

  /**
   * Parses a single tag.
   */
  private function parseTag(string $tag): ?Element {
    try {
      $document = HTMLDocument::createFromString($tag, LIBXML_NOERROR, 'UTF-8');
    }
    catch (\Throwable) {
      return NULL;
    }

    $element = $document->body?->firstElementChild;

    return $element instanceof Element ? $element : NULL;
  }

}
