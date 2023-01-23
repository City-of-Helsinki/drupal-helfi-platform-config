<?php

declare(strict_types = 1);

namespace Drupal\helfi_platform_config\Helper;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * A helper class to deal with Drupal blocks.
 */
final class BlockInstaller {

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $themeHandler
   *   The theme handler.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   */
  public function __construct(
    private ThemeHandlerInterface $themeHandler,
    private EntityTypeManagerInterface $entityTypeManager,
    private LanguageManagerInterface $languageManager,
  ) {
  }

  /**
   * Installs the given block configuration.
   *
   * Example block:
   * @code
   * [
   *   'id' => 'external_header_language_links',
   *   'plugin' => 'external_menu_block_main_navigation',
   *   'region' => 'header_branding',
   *   'settings' => [
   *     'label' => 'External - Mega menu',
   *     'depth' => 2,
   *     'expand_all_items' => TRUE,
   *   ],
   *   'translations' => [
   *     'fi' => 'Ota yhteyttä',
   *     'sv' => 'Ta kontakt',
   *   ],
   * ]
   * @endcode
   *
   * @param array $block
   *   The block config.
   * @param string|null $theme
   *   The theme.
   */
  public function install(array $block, string $theme = NULL) : void {
    $theme = $theme ?: $this->themeHandler->getDefault();

    if (!str_starts_with($theme, 'hdbt')) {
      throw new ConfigException('The default theme must be either "hdbt" or "hdbt_subtheme".');
    }

    $default = [
      'settings' => [
        'label_display' => FALSE,
        'provider' => 'helfi_navigation',
        'level' => 1,
        'depth' => 1,
        'expand_all_items' => FALSE,
      ],
      'langcode' => 'en',
      'status' => TRUE,
      'provider' => NULL,
      'theme' => $theme,
      'visibility' => [],
      'weight' => 0,
    ];

    $config = NestedArray::mergeDeep($default, $block);

    array_map(function (string $key) use ($block) : void {
      if (!isset($block[$key])) {
        throw new ConfigException(sprintf('Missing required "%s" block config.', $key));
      }
    }, ['id', 'region']);

    $this->entityTypeManager->getStorage('block')
      ->create($config)
      ->save();

    if (isset($config['translations'])) {
      $this->createTranslations($block['id'], $config['translations']);
      unset($config['translations']);
    }
  }

  /**
   * Creates translations for given block.
   *
   * @param string $id
   *   The block id.
   * @param array $translations
   *   The translations.
   */
  private function createTranslations(string $id, array $translations) : void {
    foreach ($this->languageManager->getLanguages() as $language) {
      if ($language->isDefault() || !isset($translations[$language->getId()])) {
        continue;
      }
      $configOverride = $this->languageManager
        ->getLanguageConfigOverride($language->getId(), "block.block.$id");
      $configOverride
        ->setData([
          'settings' => [
            'label' => $translations[$language->getId()],
          ],
        ])
        ->save();
    }
  }

}
