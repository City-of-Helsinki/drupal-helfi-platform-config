<?php

declare(strict_types=1);

namespace Drupal\hdbt_admin_tools\Plugin\CKEditorPlugin;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ckeditor\CKEditorPluginBase;

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
final class QuoteCKEditorButton extends CKEditorPluginBase implements ContainerFactoryPluginInterface {

  use CKEditorPluginTrait;

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
    return $this->extensionPathResolver
      ->getPath('module', 'hdbt_admin_tools') .
      '/assets/js/plugins/quote/plugin.js';
  }

}
