<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\ConfigUpdate;

use Drupal\Core\Config\ConfigInstallerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Site\Settings;
use Drupal\config_rewrite\ConfigRewriterInterface;
use Drupal\user\Entity\Role;

/**
 * A helper class to deal with config updates.
 */
final class ConfigUpdater {

  /**
   * Whether to skip update tasks.
   *
   * @var bool
   */
  private bool $skipUpdate;

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Config\ConfigInstallerInterface $configInstaller
   *   The config installer service.
   * @param \Drupal\config_rewrite\ConfigRewriterInterface $configRewriter
   *   The config rewriter service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(
    private ConfigInstallerInterface $configInstaller,
    private ConfigRewriterInterface $configRewriter,
    private ModuleHandlerInterface $moduleHandler,
  ) {
    $this->skipUpdate = Settings::get('is_azure', FALSE);
  }

  /**
   * Updates role permissions.
   *
   * @param array $permissionMap
   *   The role => permissions map.
   */
  public function updatePermissions(array $permissionMap) : void {
    foreach ($permissionMap as $rid => $permissions) {
      if (!$role = Role::load($rid)) {
        throw new \InvalidArgumentException("Role ($rid) not found.");
      }
      array_map(fn (string $permission) => $role->grantPermission($permission), $permissions);
      $role->save();
    }
  }

  /**
   * Re-import all configuration for given module.
   *
   * @param string $module
   *   The module.
   */
  public function update(string $module) : void {
    // These hooks should only be run on CI/local machine since the
    // exported configuration should be up-to-date already.
    if ($this->skipUpdate) {
      return;
    }
    $this->configInstaller->installDefaultConfig('module', $module);
    $this->configRewriter->rewriteModuleConfig($module);

    // Collect module permissions and update them.
    $permissions = $this->moduleHandler->invokeAll('platform_config_grant_permissions');
    $this->updatePermissions($permissions ?? []);

    // Update all paragraph field handlers.
    helfi_platform_config_update_paragraph_target_types();
  }

}
