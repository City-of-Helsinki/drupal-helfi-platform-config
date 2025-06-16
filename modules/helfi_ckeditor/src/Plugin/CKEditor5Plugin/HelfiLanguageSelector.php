<?php

declare(strict_types=1);

namespace Drupal\helfi_ckeditor\Plugin\CKEditor5Plugin;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\editor\EditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * CKEditor 5 HelfiLanguageSelector plugin.
 *
 * @internal
 *   Plugin classes are internal.
 */
final class HelfiLanguageSelector extends CKEditor5PluginDefault implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected LanguageManagerInterface $languageManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('language_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $config = $static_plugin_config;
    $config += [
      'helfiLanguageSelector' => $this->getLanguages() +
      [
        'current_language' => $this->languageManager
          ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
          ->getId(),
      ],
    ];
    return $config;
  }

  /**
   * Get languages as an array.
   *
   * @return array
   *   Returns languages as a key and values array where key is abbreviation of
   *   the language and value is a colon separate list of language code, name
   *   and possible direction.
   */
  protected function getLanguages() {
    $list = [];

    // Manually added missing languages what are not listed
    // in LanguageManager::getStandardLanguageList().
    $missing = [
      'co' => ['Corsican'],
      'dz' => ['Dzongkha'],
      'fo' => ['Faeroese'],
      'gd' => ['Gaelic'],
      'la' => ['Latin'],
      'pt' => ['Portuguese'],
      'se' => ['Sami'],
      'sr' => ['Serbian'],
      'xh' => ['Xhosa'],
      'yi' => ['Yiddish'],
      'yo' => ['Yoruba'],
      'zh' => ['Chinese'],
      'zu' => ['Zulu'],
    ];

    // Skip the following languages.
    $skip = [
      'en-x-simple',
      'gsw-berne',
      'pt-br',
      'pt-pt',
      'sco',
      'ta-lk',
      'xx-lolspeak',
    ];

    // Generate the language_list setting as expected by the CKEditor Language
    // plugin, but key the values by the full language name so that we can sort
    // them later on.
    foreach (array_merge(LanguageManager::getStandardLanguageList(), $missing) as $code => $name) {
      $direction = empty($name[2]) ? NULL : $name[2];
      $rtl = ($direction === LanguageInterface::DIRECTION_RTL) ? 'rtl' : 'ltr';

      // Skip the languages listed in "skip" array.
      if (in_array($code, $skip)) {
        continue;
      }

      $list[$code] = [
        'title' => $name[0],
        'textDirection' => $rtl,
        'languageCode' => $code,
      ];
    }

    // Sort on full language name.
    ksort($list);

    // Move Finnish, Swedish and English to on top of the language list.
    foreach (['en', 'sv', 'fi'] as $value) {
      $move = $list[$value];
      unset($list[$value]);
      array_unshift($list, $move);
    }

    // Add language code as array key.
    return ['language_list' => array_values($list)];
  }

}
