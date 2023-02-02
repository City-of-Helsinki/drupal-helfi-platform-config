<?php

namespace Drupal\hdbt_admin_tools\Plugin\CKEditorPlugin;

use Drupal\ckeditor\CKEditorPluginBase;
use Drupal\editor\Entity\Editor;

/**
 * Defines the "quote" plugin.
 *
 * NOTE: The plugin ID ('id' key) corresponds to the CKEditor plugin name.
 * It is the first argument of the CKEDITOR.plugins.add() function in the
 * plugin.js file.
 *
 * @CKEditorPlugin(
 *   id = "quote",
 *   label = @Translation("Quote")
 * )
 */
class QuoteCKEditorButton extends CKEditorPluginBase {

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
      'quote' => [
        'label' => $this->t('Quote'),
        'image' => $this->getModuleList()->getPath('hdbt_admin_tools') . '/assets/js/plugins/quote/icons/quote.png',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFile(): string {
    // Make sure that the path to the plugin.js matches the file structure of
    // the CKEditor plugin you are implementing.
    return $this->getModuleList()->getPath('hdbt_admin_tools') . '/assets/js/plugins/quote/plugin.js';
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
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor): array {
    return [];
  }

}
