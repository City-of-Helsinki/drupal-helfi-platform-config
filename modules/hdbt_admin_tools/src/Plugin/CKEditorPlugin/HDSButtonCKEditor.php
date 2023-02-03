<?php

namespace Drupal\hdbt_admin_tools\Plugin\CKEditorPlugin;

use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\editor\Entity\Editor;
use Drupal\ckeditor\CKEditorPluginInterface;
use Drupal\ckeditor\CKEditorPluginContextualInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the "hds-button" plugin.
 *
 * @CKEditorPlugin(
 *   id = "hds-button",
 *   label = @Translation("HDS Button enabler"),
 *   module = "ckeditor"
 * )
 */
class HDSButtonCKEditor extends PluginBase implements CKEditorPluginInterface, CKEditorPluginContextualInterface, ContainerFactoryPluginInterface {

  /**
   * Extension path resolver.
   *
   * @var \Drupal\Core\Extension\ExtensionPathResolver
   */
  protected ExtensionPathResolver $extensionPathResolver;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ExtensionPathResolver $extension_path_resolver,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->extensionPathResolver = $extension_path_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) : static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('extension.path.resolver'),
    );
  }

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
    return $this->extensionPathResolver
        ->getPath('module', 'hdbt_admin_tools') .
      '/assets/js/plugins/hds-button/plugin.js';
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
