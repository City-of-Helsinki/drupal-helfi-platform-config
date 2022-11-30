<?php

declare(strict_types = 1);

namespace Drupal\hdbt_admin_editorial\Plugin\CKEditorPlugin;

use Drupal\ckeditor\CKEditorPluginBase;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\editor\Entity\Editor;

/**
 * Defines the "language_selector" plugin.
 *
 * NOTE: The plugin ID ('id' key) corresponds to the CKEditor plugin name.
 * It is the first argument of the CKEDITOR.plugins.add() function in the
 * plugin.js file.
 *
 * @CKEditorPlugin(
 *   id = "language_selector",
 *   label = @Translation("Language selector"),
 *   module = "ckeditor"
 * )
 */
class LanguageSelector extends CKEditorPluginBase {

  /**
   * {@inheritdoc}
   *
   * NOTE: The keys of the returned array corresponds to the CKEditor button
   * names. They are the first argument of the editor.ui.addButton() or
   * editor.ui.addRichCombo() functions in the plugin.js file.
   */
  public function getButtons(): array {
    // Make sure that the path to the image matches the file structure of
    // the CKEditor plugin you are implementing.
    return [
      'language_selector' => [
        'label' => $this->t('Language selector'),
        'image' => 'themes/contrib/hdbt_admin/modules/hdbt_admin_editorial/assets/js/plugins/language_selector/icons/language_selector.png',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFile(): string {
    // Make sure that the path to the plugin.js matches the file structure of
    // the CKEditor plugin you are implementing.
    return $this->getModuleList()->getPath('hdbt_admin_editorial') . '/assets/js/plugins/language_selector/plugin.js';
  }

  /**
   * {@inheritdoc}
   */
  public function isInternal(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getDependencies(Editor $editor): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries(Editor $editor): array {
    return [
      'hdbt_admin_editorial/language_selector',
      'select2_icon/select2_icon',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor): array {
    $list = [];

    // Manually added missing languages what are not listed
    // in LanguageManager::getStandardLanguageList().
    $missing = [
      'zh' => ['Chinese'],
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
      'zu' => ['Zulu'],
    ];

    // Generate the language_list setting as expected by the CKEditor Language
    // plugin, but key the values by the full language name so that we can sort
    // them later on.
    foreach (array_merge(LanguageManager::getStandardLanguageList(), $missing) as $code => $name) {
      $direction = empty($name[2]) ? NULL : $name[2];
      $rtl = ($direction === LanguageInterface::DIRECTION_RTL) ? ':rtl' : '';
      $list[$name[0]] = "$code:$name[0]$rtl";
    }

    // Sort on full language name.
    ksort($list);

    // Move Finnish, Swedish and English to on top of the language list.
    foreach (['English', 'Swedish', 'Finnish'] as $value) {
      $move = $list[$value];
      unset($list[$value]);
      array_unshift($list, $move);
    }

    return ['language_list' => array_values($list)];
  }

}
