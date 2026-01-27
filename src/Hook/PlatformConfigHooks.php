<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Hook;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\helfi_platform_config\ConfigUpdate\ConfigUpdaterInterface;
use Drupal\helfi_platform_config\ConfigUpdate\ParagraphTypeUpdater;

/**
 * Hook implementations for platform config module.
 */
class PlatformConfigHooks {

  use AutowireTrait;

  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly ConfigUpdaterInterface $configUpdater,
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

  /**
   * Implements hook_page_attachments().
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$page): void {
    if (!$this->moduleHandler->moduleExists('raven')) {
      return;
    }

    // Add sentry_ignore library.
    $page['#attached']['library'][] = 'helfi_platform_config/sentry_ignore';
  }

}
