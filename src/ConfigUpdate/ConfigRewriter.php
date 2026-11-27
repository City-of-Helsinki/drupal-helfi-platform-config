<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\ConfigUpdate;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\config_rewrite\ConfigRewriter as ConfigRewriterBase;

/**
 * A service decorating config rewriter.
 */
final class ConfigRewriter extends ConfigRewriterBase {

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   * @param \Drupal\language\Config\LanguageConfigFactoryOverrideInterface|null $language_config_factory_override
   *   The language config factory override, if the language module is enabled.
   * @param \Drupal\Core\Config\ConfigManagerInterface $configManager
   *   The config manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    ModuleHandlerInterface $module_handler,
    LoggerChannelInterface $logger,
    FileSystemInterface $file_system,
    $language_config_factory_override,
    private readonly ConfigManagerInterface $configManager,
  ) {
    parent::__construct($config_factory, $module_handler, $logger, $file_system, $language_config_factory_override);
  }

  /**
   * {@inheritdoc}
   */
  public function rewriteConfig(
    $original_config,
    $rewrite,
    $config_name,
    $extensionName,
  ) : array {
    $rewritten_config = parent::rewriteConfig($original_config, $rewrite, $config_name, $extensionName);

    if (!isset($rewrite['config_rewrite'])) {
      $rewritten_config = ConfigMergeHelper::mergeDeepArray([
        $original_config,
        $rewrite,
      ]);
    }
    return $rewritten_config;
  }

  /**
   * {@inheritdoc}
   */
  protected function rewriteDirectoryConfig($extension, $rewrite_dir, $langcode = NULL) : void {
    parent::rewriteDirectoryConfig($extension, $rewrite_dir, $langcode);

    // Language overrides are not configuration entities.
    if ($langcode || !is_dir($rewrite_dir)) {
      return;
    }

    foreach ($this->fileScanDirectory($rewrite_dir, '/^.*\.yml$/i', ['recurse' => FALSE]) as $file) {
      $this->saveConfigEntity($file->name);
    }
  }

  /**
   * Saves the rewritten configuration through the entity storage.
   *
   * The parent saves the rewritten data as simple configuration, which skips
   * the configuration entity's own processing on save. For example, entity
   * displays sort their components on save. The data would then differ from
   * what a configuration import saves, and the configuration would show up
   * as changed on every import.
   *
   * @param string $name
   *   The configuration name.
   */
  private function saveConfigEntity(string $name) : void {
    if (!$entityTypeId = $this->configManager->getEntityTypeIdByName($name)) {
      return;
    }
    $storage = $this->configManager->getEntityTypeManager()->getStorage($entityTypeId);
    $entityType = $storage->getEntityType();
    assert($storage instanceof ConfigEntityStorageInterface && $entityType instanceof ConfigEntityTypeInterface);

    // The entity might be statically cached from before the rewrite.
    $storage->resetCache();
    $entity = $storage->loadOverrideFree($storage->getIDFromConfigName($name, $entityType->getConfigPrefix()));

    // Save it the same way a configuration import does.
    if ($entity instanceof ConfigEntityInterface) {
      $entity->setSyncing(TRUE)->save();
    }
  }

}
