<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Drush\Commands;

use Drupal\config_rewrite\ConfigRewriterInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\helfi_platform_config\ConfigUpdate\ConfigUpdaterInterface;
use Drush\Attributes\Command;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Provides a Drush command to update all configuration at once.
 */
final class ConfigUpdaterCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    private ConfigUpdaterInterface $configUpdater,
    #[Autowire(service: 'config_rewrite.config_rewriter')] private ConfigRewriterInterface $configRewriter,
    private ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct();
  }

  /**
   * Scans all available sub-modules.
   *
   * @param string $basePath
   *   Base path of the modules.
   * @param string $type
   *   The type of config to update.
   * @param array $ignore
   *   Modules to ignore.
   *
   * @return array
   *   An array of module names.
   */
  private function getModules(string $basePath, string $type = 'install', array $ignore = []) : array {
    $iterator = new \DirectoryIterator($basePath);

    $modules = [];
    foreach ($iterator as $module) {
      if (!$module->isDir() || $module->isDot()) {
        continue;
      }
      // Make sure module has config to install.
      if (!is_dir($module->getPathname() . '/config/' . $type)) {
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
  public function update(?string $moduleName = NULL): int {
    $ignore = [
      // Never update helfi_user_roles module because it will mess up
      // user permissions and dependencies.
      // This shouldn't be needed anyway since it provides no real
      // configuration besides the skeleton user roles with no dependencies.
      'helfi_user_roles',
    ];
    $modules = [$moduleName];

    if (!$moduleName) {
      $module = $this->moduleHandler->getModule('helfi_platform_config');

      $submodules = $this->getModules($module->getPath() . '/modules/', ignore: $ignore);
      $modules = ['helfi_platform_config', ...$submodules];
    }
    foreach ($modules as $name) {
      $this->configUpdater->update($name);
    }

    // Handle custom module rewrites.
    $path = DRUPAL_ROOT . '/modules/custom';

    if (is_dir($path)) {
      foreach ($this->getModules($path, 'rewrite') as $name) {
        $this->configRewriter->rewriteModuleConfig($name);
      }
    }
    return DrushCommands::EXIT_SUCCESS;
  }

}
