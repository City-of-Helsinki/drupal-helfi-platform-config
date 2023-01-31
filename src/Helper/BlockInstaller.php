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
   *
   * @code
   * $block = [
   *   'id' => 'external_header_language_links',
   *   'plugin' => 'external_menu_block_main_navigation',
   *   'settings' => [
   *     'label' => 'External - Mega menu',
   *     'label_display' => TRUE,
   *     'depth' => 2,
   *     'expand_all_items' => TRUE,
   *   ],
   *   'provider' => 'helfi_navigation',
   *   'translations' => [
   *     'fi' => 'Ota yhteyttä',
   *     'sv' => 'Ta kontakt',
   *   ],
   * ];
   * $variations = [
   *   [
   *     'theme' => 'hdbt',
   *     'region' => 'sidebar',
   *   ],
   *   [
   *     'theme' => 'stark',
   *     'region' => 'content',
   *   ],
   * ];
   * @endcode
   *
   * @param array $block
   *   The block config.
   * @param array $variations
   *   The supported theme variations.
   */
  public function install(array $block, array $variations) : void {
    $installed = 0;
    $storage = $this->entityTypeManager->getStorage('block');

    foreach ($variations as $variation) {
      if (!isset($variation['theme'], $variation['region'])) {
        throw new ConfigException('Missing required "theme" or "region" variation.');
      }

      ['theme' => $theme, 'region' => $region] = $variation;

      // Skip if theme is not installed.
      if (!$this->themeHandler->themeExists($theme)) {
        continue;
      }
      $default = [
        'settings' => [
          'label_display' => FALSE,
        ],
        'theme' => $theme,
        'langcode' => 'en',
        'status' => TRUE,
        'visibility' => [],
        'weight' => 0,
        'region' => $region,
      ];

      $config = NestedArray::mergeDeep($default, $block);

      if (!isset($config['id'], $config['provider'])) {
        throw new ConfigException('Missing required "id" or "provider" block config.');
      }

      if (!$storage->load($config['id'])) {
        $storage->create($config)->save();
        $installed++;
      }
    }

    if ($installed > 0 && isset($config['translations'])) {
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
