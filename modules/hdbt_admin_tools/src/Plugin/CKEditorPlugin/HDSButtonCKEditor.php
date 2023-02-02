<?php

namespace Drupal\hdbt_admin_tools\Plugin\CKEditorPlugin;

use Drupal\Core\Plugin\PluginBase;
use Drupal\editor\Entity\Editor;
use Drupal\ckeditor\CKEditorPluginInterface;
use Drupal\ckeditor\CKEditorPluginContextualInterface;

/**
 * Defines the "hds-button" plugin.
 *
 * @CKEditorPlugin(
 *   id = "hds-button",
 *   label = @Translation("HDS Button enabler"),
 *   module = "ckeditor"
 * )
 */
class HDSButtonCKEditor extends PluginBase implements CKEditorPluginInterface, CKEditorPluginContextualInterface {

  /**
   * {@inheritdoc}
   */
  public function isEnabled(Editor $editor): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getFile(): string {
    return $this->getModuleList()->getPath('hdbt_admin_tools') . '/assets/js/plugins/hds-button/plugin.js';
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
