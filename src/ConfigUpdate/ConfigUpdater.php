<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\ConfigUpdate;

use Drupal\Core\Config\ConfigInstallerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\Dependency;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Site\Settings;
use Drupal\config_rewrite\ConfigRewriterInterface;

/**
 * A helper class to deal with config updates.
 */
final class ConfigUpdater implements ConfigUpdaterInterface {

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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\helfi_platform_config\ConfigUpdate\ParagraphTypeUpdater $paragraphTypeUpdater
   *   The paragraph type updater.
   * @param \Drupal\Core\Extension\ModuleInstallerInterface $moduleInstaller
   *   The module installer.
   * @param \Drupal\Core\Extension\ModuleExtensionList $moduleExtensionList
   *   The module extension list.
   */
  public function __construct(
    private ConfigInstallerInterface $configInstaller,
    private ConfigRewriterInterface $configRewriter,
    private ModuleHandlerInterface $moduleHandler,
    private EntityTypeManagerInterface $entityTypeManager,
    private ParagraphTypeUpdater $paragraphTypeUpdater,
    private ModuleInstallerInterface $moduleInstaller,
    private ModuleExtensionList $moduleExtensionList,
  ) {
    $this->skipUpdate = Settings::get('is_azure', FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function updatePermissions(array $permissionMap) : void {
    foreach ($permissionMap as $rid => $permissions) {
      if (!$role = $this->entityTypeManager->getStorage('user_role')->load($rid)) {
        throw new \InvalidArgumentException("Role ($rid) not found.");
      }
      array_map(fn (string $permission) => $role->grantPermission($permission), $permissions);
      $role->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function update(string $module) : void {
    // These hooks should only be run on CI/local machine since the
    // exported configuration should be up-to-date already.
    if ($this->skipUpdate) {
      return;
    }
    $this->installMissingDependencies($module);
    $this->configInstaller->installDefaultConfig('module', $module);
    $this->configRewriter->rewriteModuleConfig($module);

    // Collect module permissions and update them.
    $permissions = $this->moduleHandler->invokeAll('platform_config_grant_permissions');
    $this->updatePermissions($permissions ?? []);

    // Update all paragraph field handlers.
    $this->paragraphTypeUpdater->updateParagraphTargetTypes();
  }

  /**
   * Installs dependencies added to the module after it was installed.
   *
   * Configuration and permissions provided by the module may depend on them.
   *
   * @param string $module
   *   The module.
   */
  private function installMissingDependencies(string $module) : void {
    $dependencies = $this->moduleExtensionList->getExtensionInfo($module)['dependencies'] ?? [];

    $missing = array_filter(
      array_map(fn (string $dependency) => Dependency::createFromString($dependency)->getName(), $dependencies),
      fn (string $name) => !$this->moduleHandler->moduleExists($name),
    );

    if ($missing) {
      $this->moduleInstaller->install(array_values($missing));
    }
  }

}
