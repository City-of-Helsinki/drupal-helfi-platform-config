<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderAfter;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Alters the path alias add and edit forms.
 */
final class PathAliasFormHooks {

  use StringTranslationTrait;

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LanguageManagerInterface $languageManager,
  ) {
  }

  /**
   * Implements hook_field_widget_single_element_form_alter().
   *
   * Runs after the path module, which sets the description and the full
   * base URL as the prefix of the path field.
   *
   * @param array<string, mixed> $element
   *   The field widget form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array<string, mixed> $context
   *   The widget context.
   */
  #[Hook('field_widget_single_element_form_alter', order: new OrderAfter(modules: ['path']))]
  public function fieldWidgetSingleElementFormAlter(array &$element, FormStateInterface $form_state, array $context): void {
    $fieldDefinition = $context['items']->getFieldDefinition();

    if (
      $fieldDefinition->getTargetEntityTypeId() !== 'path_alias' ||
      $fieldDefinition->getName() !== 'path' ||
      $context['widget']->getPluginId() !== 'string_textfield'
    ) {
      return;
    }

    $element['value']['#description'] = $this->t('Add the final part of the URL, meaning everything that comes after the instance.');
    // Keep the base URL set by the path module and append the instance path.
    $element['value']['#field_prefix'] = rtrim((string) ($element['value']['#field_prefix'] ?? ''), '/') . $this->getInstancePrefix();
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
