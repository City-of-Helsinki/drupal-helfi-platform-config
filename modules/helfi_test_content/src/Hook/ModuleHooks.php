<?php

declare(strict_types=1);

namespace Drupal\helfi_test_content\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
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
    private readonly ConfigFactoryInterface $configFactory,
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

    if (in_array('helfi_test_content', $modules)) {
      // Install instance specific test content if the helfi_test_content has
      // been installed.
      if (array_key_exists('helfi_custom_test_content', $this->moduleExtensionList->getList())) {
        /** @var \Drupal\Core\Extension\ModuleInstallerInterface $moduleInstaller */
        $this->moduleInstaller->install(['helfi_custom_test_content']);
      }

      // Set the announcement remote entities setting to false.
      $announcement_config = $this->configFactory->getEditable('block.block.announcements');
      $announcement_settings = $announcement_config->get('settings') ?? [];
      $announcement_settings['use_remote_entities'] = FALSE;
      $announcement_config->set('settings', $announcement_settings)->save();
    }
  }

}
