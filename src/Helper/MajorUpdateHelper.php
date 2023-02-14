<?php

declare(strict_types = 1);

namespace Drupal\helfi_platform_config\Helper;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Update\UpdateHookRegistry;

/**
 * Helper class to perform 2.x to 3.x update.
 */
final class MajorUpdateHelper {

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Update\UpdateHookRegistry $updateHookRegistry
   *   The update hook registry.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service.
   * @param \Drupal\Core\Extension\ModuleExtensionList $moduleExtensionList
   *   The module extension list service.
   */
  public function __construct(
    private UpdateHookRegistry $updateHookRegistry,
    private Connection $database,
    private ModuleHandlerInterface $moduleHandler,
    private ModuleExtensionList $moduleExtensionList,
  ) {
  }

  /**
   * Determines whether updates should be applied or not.
   *
   * @return bool
   *   TRUE if hook needs to be run.
   */
  public function needsUpdate() : bool {
    return $this->updateHookRegistry
      ->getInstalledVersion('helfi_platform_config') > 9000;
  }

  /**
   * Replaces the config for given module.
   *
   * @param string $configExportFolder
   *   The configuration export folder.
   * @param string $module
   *   The nodule config to replace.
   */
  public function replaceConfig(string $configExportFolder, string $module) : void {
    $configFolder = sprintf('%s/config', $this->moduleExtensionList->getPath($module));

    if (!is_dir($configFolder)) {
      return;
    }
    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($configFolder)
    );
    foreach ($iterator as $item) {
      if (!$item->isFile()) {
        continue;
      }
      $fileName = str_replace($configFolder, '', $item->getPathname());

      if (str_contains($fileName, 'schema')) {
        continue;
      }
      $fileName = str_replace(['rewrite', 'optional', 'install'], '', $fileName);
      $fileName = ltrim($fileName, '/');

      $fileContent = file_get_contents($item->getPathName());
      file_put_contents($configExportFolder . '/' . $fileName, $fileContent);
    }
  }

  /**
   * Disables modules without running uninstall tasks.
   *
   * @param array $modules
   *   The module list.
   */
  public function forceDisableModules(array $modules) : void {
    foreach ($modules as $oldModule => $newModule) {
      $this->database->delete('key_value')
        ->condition('name', $oldModule)
        ->execute();
    }
  }

  /**
   * Enables modules without running installation tasks.
   *
   * @param array $modules
   *   The module list.
   */
  public function forceEnableModules(array $modules) : void {
    foreach ($modules as $module) {
      if ($this->updateHookRegistry->getInstalledVersion($module) > UpdateHookRegistry::SCHEMA_UNINSTALLED) {
        continue;
      }
      $this->database->insert('key_value')
        ->fields([
          'collection' => 'system.schema',
          'name' => $module,
          'value' => 9000,
        ])
        ->execute();
    }
  }

  /**
   * Gets modules that needs to be enabled.
   *
   * A list of modules that needs to be enabled and can be installed
   * using regular installation method.
   *
   * @return array
   *   The module list.
   */
  public function getDependencies(array $modules) : array {
    $dependencies = [];
    foreach ($modules as $module) {
      $extensionInfo = $this->moduleExtensionList->get($module);

      if (!isset($extensionInfo->requires)) {
        continue;
      }
      foreach ($extensionInfo->requires as $dependency => $object) {
        // Skip already enabled modules.
        if ($this->moduleHandler->moduleExists($dependency)) {
          continue;
        }
        $dependencies[] = $dependency;
      }
    }
    return $dependencies;
  }

  /**
   * Gets the base modules.
   *
   * @return array
   *   An array of base modules.
   */
  public function getBaseModules() : array {
    static $modules = [];

    if ($modules) {
      return $modules;
    }
    $path = $this->moduleExtensionList->getPath('helfi_platform_config_base');
    $fileContent = Yaml::decode(file_get_contents($path . '/helfi_platform_config_base.info.yml'));

    return $modules = array_map(function (string $line) : string {
      return explode(':', $line)[0];

    }, $fileContent['dependencies']);
  }

}
