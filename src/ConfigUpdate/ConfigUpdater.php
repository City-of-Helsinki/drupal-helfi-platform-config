<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\ConfigUpdate;

use Drupal\config_rewrite\ConfigRewriterInterface;
use Drupal\Core\Config\ConfigInstallerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * A helper class to deal with config updates.
 */
final class ConfigUpdater {

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

    if ($module === 'helfi_base_content') {
      // Rewrite helfi_tpr_config configuration if the helfi_base_content is
      // being updated.
      if ($this->moduleHandler->moduleExists('helfi_tpr_config')) {
        $this->configRewriter->rewriteModuleConfig('helfi_tpr_config');
      }
    }

    // Allow modules to rewrite config based on updated modules.
    $this->moduleHandler->invokeAll('rewrite_config_update', [
      $module,
      $this->configRewriter,
    ]);

    // Update all paragraph field handlers.
    helfi_platform_config_update_paragraph_target_types();
  }

}
