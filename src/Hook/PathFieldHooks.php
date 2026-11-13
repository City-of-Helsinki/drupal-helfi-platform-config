<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Alters the path fields of the path alias and redirect forms.
 */
final class PathFieldHooks {

  use StringTranslationTrait;

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LanguageManagerInterface $languageManager,
  ) {
  }

  /**
   * Implements hook_form_FORM_ID_alter() for "path_alias_form".
   *
   * Alters the "System path" field of the path alias add and edit forms.
   *
   * @param array<string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  #[Hook('form_path_alias_form_alter')]
  public function pathAliasFormAlter(array &$form, FormStateInterface $form_state): void {
    if (isset($form['path']['widget'][0]['value'])) {
      $this->alterPathElement($form['path']['widget'][0]['value']);
    }
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for "redirect_form".
   *
   * Alters the "From" field of the redirect add and edit forms.
   *
   * @param array<string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  #[Hook('form_redirect_form_alter')]
  public function redirectFormAlter(array &$form, FormStateInterface $form_state): void {
    if (isset($form['redirect_source']['widget'][0]['path'])) {
      // The redirect module ends the prefix with a slash to show that the path
      // is entered without a leading slash, so keep it.
      $this->alterPathElement($form['redirect_source']['widget'][0]['path'], '/');
    }
  }

  /**
   * Sets the description and the instance URL prefix of a path element.
   *
   * @param array<string, mixed> $element
   *   The textfield element.
   * @param string $suffix
   *   A string to append to the prefix.
   */
  private function alterPathElement(array &$element, string $suffix = ''): void {
    $element['#description'] = $this->t('Add the final part of the URL, meaning everything that comes after the instance.');
    // Keep the base URL set by the original widget and append the instance
    // path.
    $element['#field_prefix'] = rtrim((string) ($element['#field_prefix'] ?? ''), '/') . $this->getInstancePrefix() . $suffix;
  }

  /**
   * Builds the instance URL prefix for the current language.
   *
   * For example "/fi" or "/fi/sosiaali-ja-terveyspalvelut".
   *
   * @return string
   *   The language prefix followed by the site prefix, if any.
   */
  private function getInstancePrefix(): string {
    $langcode = $this->languageManager
      ->getCurrentLanguage(LanguageInterface::TYPE_URL)
      ->getId();

    $languagePrefixes = $this->configFactory->get('language.negotiation')->get('url.prefixes') ?? [];
    $sitePrefixes = $this->configFactory->get('helfi_proxy.settings')->get('prefixes') ?? [];

    $parts = array_filter([
      $languagePrefixes[$langcode] ?? NULL,
      $sitePrefixes[$langcode] ?? NULL,
    ], static fn (mixed $part): bool => is_string($part) && $part !== '');

    return $parts ? '/' . implode('/', $parts) : '';
  }

}
