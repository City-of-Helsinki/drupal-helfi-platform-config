<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\ConfigUpdate;

/**
 * Defines an interface for configuration updater services.
 */
interface ConfigUpdaterInterface {

  /**
   * Updates role permissions.
   *
   * @param array $permissionMap
   *   The role => permissions map.
   */
  public function updatePermissions(array $permissionMap): void;

  /**
   * Re-imports all configuration for given module.
   *
   * @param string $module
   *   The module.
   */
  public function update(string $module): void;

}
