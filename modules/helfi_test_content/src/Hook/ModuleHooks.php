<?php

declare(strict_types=1);

namespace Drupal\helfi_test_content\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;

/**
 * Module hook implementations for modules.
 */
class ModuleHooks {

  use AutowireTrait;

  public function __construct(
    private readonly ModuleExtensionList $moduleExtensionList,
    private readonly ModuleInstallerInterface $moduleInstaller,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EnvironmentResolverInterface $environmentResolver,
  ) {
  }

  /**
   * Checks whether the active environment is production.
   *
   * @return bool
   *   TRUE if the active environment is production.
   */
  private function isProduction(): bool {
    return $this->environmentResolver->getActiveEnvironmentName() === EnvironmentEnum::Prod->value;
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
      // Never alter production configuration or install more test content.
      if ($this->isProduction()) {
        return;
      }

      // Install instance specific test content if the helfi_test_content has
      // been installed.
      if (array_key_exists('helfi_custom_test_content', $this->moduleExtensionList->getList())) {
        $this->moduleInstaller->install(['helfi_custom_test_content']);
      }

      // Show remote announcements from fixtures.
      $announcement_config = $this->configFactory->getEditable('block.block.announcements');
      $announcement_settings = $announcement_config->get('settings') ?? [];
      $announcement_settings['use_remote_entities'] = TRUE;
      $announcement_config->set('settings', $announcement_settings);

      // Show announcements only on the announcements test page.
      $announcement_config->set('visibility.request_path', [
        'id' => 'request_path',
        'negate' => FALSE,
        'pages' => '/dc-components/dc-announcements',
      ]);
      $announcement_config->save();
    }
  }

}
