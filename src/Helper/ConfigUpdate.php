<?php

declare(strict_types = 1);

namespace Drupal\helfi_platform_config\Helper;

use Drupal\config_rewrite\ConfigRewriterInterface;
use Drupal\Core\Config\ConfigInstallerInterface;

/**
 * A helper class to deal with config updates.
 */
final class ConfigUpdate {

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Config\ConfigInstallerInterface $configInstaller
   *   The config installer service.
   * @param \Drupal\config_rewrite\ConfigRewriterInterface $configRewriter
   *   The config rewriter service.
   */
  public function __construct(
    private ConfigInstallerInterface $configInstaller,
    private ConfigRewriterInterface $configRewriter,
  ) {
  }

  /**
   * Re-import all configuration for given module.
   *
   * @param string $module
   *   The module.
   */
  public function update(string $module) : void {
    $this->configInstaller->installDefaultConfig('module', $module);
    $this->configRewriter->rewriteModuleConfig($module);

    // Update all paragraph field handlers.
    helfi_platform_config_update_paragraph_target_types();
  }

}
