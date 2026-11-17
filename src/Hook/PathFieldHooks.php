<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Hook;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\Markup;
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
      $langcode = $this->getSelectedLangcode($form, $form_state, 'langcode');
      $this->alterPathElement($form['path']['widget'][0]['value'], $langcode, 'langcode');
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
      $langcode = $this->getSelectedLangcode($form, $form_state, 'language');
      $this->alterPathElement($form['redirect_source']['widget'][0]['path'], $langcode, 'language', '/');
    }
  }

  /**
   * Sets the description and the instance URL prefix of a path element.
   *
   * The instance prefix follows the language selected in the language field
   * of the same form. When no language is selected, only the base URL is
   * shown. A script updates the prefix when the language is changed.
   *
   * @param array<string, mixed> $element
   *   The path textfield element.
   * @param string $langcode
   *   The language selected in the language field.
   * @param string $languageField
   *   The name of the language field in the form.
   * @param string $suffix
   *   A string to append to the prefix.
   */
  private function alterPathElement(array &$element, string $langcode, string $languageField, string $suffix = ''): void {
    $element['#description'] = $this->t('Add the final part of the URL, meaning everything that comes after the instance.');

    // Keep the base URL set by the original widget and append the instance
    // path of each language.
    $baseUrl = rtrim((string) ($element['#field_prefix'] ?? ''), '/');
    $prefixes = ['' => $baseUrl . $suffix];
    foreach (array_keys($this->languageManager->getLanguages()) as $prefixLangcode) {
      $prefixes[$prefixLangcode] = $baseUrl . $this->getInstancePrefix($prefixLangcode) . $suffix;
    }

    $element['#field_prefix'] = Markup::create(sprintf(
      '<span data-helfi-instance-prefix>%s</span>',
      Html::escape($prefixes[$langcode] ?? $prefixes['']),
    ));
    $element['#attributes']['data-helfi-instance-prefixes'] = Json::encode($prefixes);
    $element['#attributes']['data-helfi-language-field'] = $languageField . '[0][value]';
    $element['#attached']['library'][] = 'helfi_platform_config/instance_prefix';
  }

  /**
   * Gets the language selected in the language field of the form.
   *
   * @param array<string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $languageField
   *   The name of the language field.
   *
   * @return string
   *   The selected langcode.
   */
  private function getSelectedLangcode(array $form, FormStateInterface $form_state, string $languageField): string {
    // Submitted value, e.g. when the form is shown again after a validation
    // error.
    $input = $form_state->getUserInput()[$languageField][0]['value'] ?? NULL;
    if (is_string($input)) {
      return $input;
    }
    return (string) ($form[$languageField]['widget'][0]['value']['#default_value'] ?? '');
  }

  /**
   * Builds the instance URL prefix for the given language.
   *
   * For example "/fi" or "/fi/sosiaali-ja-terveyspalvelut".
   *
   * @param string $langcode
   *   The langcode.
   *
   * @return string
   *   The language prefix followed by the site prefix, if any.
   */
  private function getInstancePrefix(string $langcode): string {
    $languagePrefixes = $this->configFactory->get('language.negotiation')->get('url.prefixes') ?? [];
    $sitePrefixes = $this->configFactory->get('helfi_proxy.settings')->get('prefixes') ?? [];

    // Languages without a URL prefix, such as "Not specified", have no
    // instance prefix either.
    $languagePrefix = $languagePrefixes[$langcode] ?? NULL;
    if (!is_string($languagePrefix) || $languagePrefix === '') {
      return '';
    }

    $parts = [$languagePrefix];
    $sitePrefix = $sitePrefixes[$langcode] ?? NULL;
    if (is_string($sitePrefix) && $sitePrefix !== '') {
      $parts[] = $sitePrefix;
    }
    return '/' . implode('/', $parts);
  }

}
