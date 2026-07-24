<?php

declare(strict_types=1);

namespace Drupal\helfi_toc\Hook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Theme hooks for HELfi Table of contents.
 */
class ThemeHooks {

  use StringTranslationTrait;

  public function __construct(
    private readonly ModuleExtensionList $moduleExtensionList,
  ) {
  }

  /**
   * Implements hook_theme().
   *
   * @return array<string, mixed>
   *   The theme definitions.
   */
  #[Hook('theme')]
  public function theme(): array {
    $module_path = $this->moduleExtensionList->getPath('helfi_toc');

    return [
      'field__toc_enabled' => [
        'base hook' => 'field',
        'render element' => 'element',
        'template' => 'field--toc-enabled',
        'path' => "$module_path/templates",
      ],
    ];
  }

  /**
   * Implements hook_theme_suggestions_HOOK_alter() for field.
   *
   * @param array<int, string> $suggestions
   *   The theme suggestions.
   * @param array<string, mixed> $variables
   *   The theme variables.
   */
  #[Hook('theme_suggestions_field_alter')]
  public function themeSuggestionsFieldAlter(array &$suggestions, array $variables): void {
    if (($variables['element']['#field_name'] ?? NULL) === 'toc_enabled') {
      $suggestions[] = 'field__toc_enabled';
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for field__toc_enabled.
   *
   * @param array<string, mixed> $variables
   *   The theme variables.
   */
  #[Hook('preprocess_field__toc_enabled')]
  public function preprocessFieldTocEnabled(array &$variables): void {
    $entity = $variables['element']['#object'];

    if (!$entity instanceof ContentEntityInterface) {
      return;
    }

    $variables['toc_enabled'] = (bool) $entity->get('toc_enabled')->value;
    $variables['toc_title'] = $this->t('On this page');
    if ($variables['toc_enabled']) {
      $variables['#attached']['library'][] = 'helfi_toc/table_of_contents';
    }
  }

}
