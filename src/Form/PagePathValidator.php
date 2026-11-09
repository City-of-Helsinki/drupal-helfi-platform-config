<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Validates and normalizes page path pattern fields.
 */
final class PagePathValidator {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Validates page path patterns.
   *
   * Each line must be the front page token or start with a slash, and must
   * not include a language or site prefix. Patterns that match every page
   * are rejected. Surrounding whitespace is removed from each stored line.
   *
   * @param array<string, mixed> $element
   *   The path textarea element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array<string, mixed> $complete_form
   *   The complete form. Required by the element validate callback signature.
   *
   * @param-out array<mixed, mixed> $element
   *   The same element. Form error handling accepts any array shape.
   */
  public function validate(array &$element, FormStateInterface $form_state, array &$complete_form): void {
    $value = $element['#value'] ?? '';
    if (!is_string($value) || $value === '') {
      return;
    }

    $languagePrefixes = $this->configuredPrefixes('language.negotiation', 'url.prefixes');
    $sitePrefixes = $this->configuredPrefixes('helfi_proxy.settings', 'prefixes');

    $paths = array_map('trim', explode("\n", $value));
    foreach ($paths as $path) {
      if ($path === '' || $path === '<front>') {
        continue;
      }
      if ($path === '*' || $path === '/*') {
        $form_state->setError($element, new TranslatableMarkup('The path %path matches every page. Leave the field empty instead.', [
          '%path' => $path,
        ]));
        return;
      }
      if (!str_starts_with($path, '/')) {
        $form_state->setError($element, new TranslatableMarkup('The path %path requires a leading forward slash.', [
          '%path' => $path,
        ]));
        return;
      }

      $suggestion = $this->suggestionWithoutPrefixes($path, $languagePrefixes, $sitePrefixes);
      if ($suggestion === NULL) {
        continue;
      }
      $form_state->setError($element, new TranslatableMarkup('The path %path includes a language or site prefix. Use %suggestion instead.', [
        '%path' => $path,
        '%suggestion' => $suggestion,
      ]));
      return;
    }

    // The submitted value is copied into form state before this callback.
    // Write the trimmed lines back so the stored value matches validation.
    $normalized = implode("\n", $paths);
    $element['#value'] = $normalized;
    $form_state->setValueForElement($element, $normalized);
  }

  /**
   * Collects configured path prefixes, keyed by the lowercase prefix.
   *
   * @return array<string, true>
   *   Prefixes that must not be entered.
   */
  private function configuredPrefixes(string $configName, string $key): array {
    $prefixes = $this->configFactory->get($configName)->get($key);
    if (!is_array($prefixes)) {
      return [];
    }

    $normalized = [];
    foreach ($prefixes as $prefix) {
      if (!is_string($prefix)) {
        continue;
      }
      $prefix = mb_strtolower(trim($prefix, '/'));
      if ($prefix !== '') {
        $normalized[$prefix] = TRUE;
      }
    }
    return $normalized;
  }

  /**
   * Returns the path with leading language and site prefixes removed.
   *
   * @param string $path
   *   The entered path.
   * @param array<string, true> $languagePrefixes
   *   Language URL prefixes.
   * @param array<string, true> $sitePrefixes
   *   Site instance prefixes from helfi_proxy.
   *
   * @return string|null
   *   The corrected path, or NULL when the path has no leading prefix.
   */
  private function suggestionWithoutPrefixes(string $path, array $languagePrefixes, array $sitePrefixes): ?string {
    $segments = array_values(array_filter(explode('/', $path), static fn (string $segment): bool => $segment !== ''));
    $removed = FALSE;

    while ($segments) {
      $segment = mb_strtolower($segments[0]);
      if (!isset($languagePrefixes[$segment]) && !isset($sitePrefixes[$segment])) {
        break;
      }
      array_shift($segments);
      $removed = TRUE;
    }

    if (!$removed) {
      return NULL;
    }
    if (!$segments) {
      return '<front>';
    }
    return '/' . implode('/', $segments);
  }

}
