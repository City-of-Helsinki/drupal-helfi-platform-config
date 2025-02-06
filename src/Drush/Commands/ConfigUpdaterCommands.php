<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Drush\Commands;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\helfi_platform_config\ConfigUpdate\ConfigUpdater;
use Drush\Attributes\Command;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * Provides a Drush command to update all configuration at once.
 */
final class ConfigUpdaterCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    private ConfigUpdater $configUpdater,
    private ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct();
  }

  /**
   * Scans all available sub-modules.
   *
   * @return array
   *   An array of module names.
   */
  private function getModules() : array {
    $ignore = [
      // Never update helfi_user_roles module because it will mess up
      // all user roles and their dependencies.
      // This shouldn't be needed anyway since it provides no real
      // configuration besides the bare minimum user roles.
      // 'helfi_user_roles',
    ];
    $iterator = new \DirectoryIterator(__DIR__ . '/../../../modules');

    $modules = [];
    foreach ($iterator as $module) {
      if (!$module->isDir() || $module->isDot()) {
        continue;
      }
      // Make sure module has config to install.
      if (!is_dir($module->getPathname() . '/config/install')) {
        continue;
      }
      $name = $module->getBasename();

      // Skip ignored and not-installed modules.
      if (!$this->moduleHandler->moduleExists($name) || in_array($name, $ignore)) {
        continue;
      }

      $modules[] = $name;
    }
    return $modules;
  }

  /**
   * Scans all available modules and updates the configuration.
   *
   * @return int
   *   The exit code.
   */
  #[Command(name: 'helfi:platform-config:update')]
  public function update(): int {
    foreach ($this->getModules() as $name) {
      $this->configUpdater->update($name);
    }
    return DrushCommands::EXIT_SUCCESS;
  }

}
