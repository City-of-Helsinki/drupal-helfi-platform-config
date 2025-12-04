<?php

declare(strict_types=1);

namespace Drupal\helfi_image_styles\Hook;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Module hook implementations for modules.
 */
class ModuleHooks {

  use AutowireTrait;

  public function __construct(
    private readonly ModuleExtensionList $moduleExtensionList,
    private readonly ModuleInstallerInterface $moduleInstaller,
  ) {
  }

  /**
   * Implements hook_module_preinstall().
   */
  #[Hook('module_preinstall')]
  public function modulePreinstall(string $module, bool $is_syncing): void {

    if ($is_syncing || $module !== 'helfi_image_styles') {
      return;
    }

    if (array_key_exists('imagemagick', $this->moduleExtensionList->getList())) {
      $this->moduleInstaller->install(['imagemagick']);
    }
  }

}
