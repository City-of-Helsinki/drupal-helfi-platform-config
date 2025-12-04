<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Hook;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\helfi_platform_config\ConfigUpdate\ConfigUpdater;
use Drupal\helfi_platform_config\DTO\ParagraphTypeCollection;

/**
 * Module hook implementations for modules.
 */
class ModuleHooks {

  use AutowireTrait;

  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly ConfigUpdater $configUpdater,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly ParagraphTypeUpdater $paragraphTypeUpdater,
  ) {
  }

  /**
   * Implements hook_modules_installed().
   */
  #[Hook('modules_installed')]
  public function modulesInstalled(array $modules, bool $is_syncing): void {

    if ($is_syncing) {
      return;
    }

    if ($this->moduleHandler->moduleExists('locale')) {
      locale_system_set_config_langcodes();
    }

    foreach ($modules as $module) {
      $permissions = $this->moduleHandler->invoke($module, 'platform_config_grant_permissions');
      $this->configUpdater->updatePermissions($permissions ?? []);
    }

    $this->paragraphTypeUpdater->updateParagraphTargetTypes();
  }

}
