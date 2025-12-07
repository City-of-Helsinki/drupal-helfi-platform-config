<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_test_content\Unit\Hook;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\helfi_test_content\Hook\ModuleHooks;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the module hooks.
 *
 * @group helfi_test_content
 */
final class ModuleHooksTest extends UnitTestCase {

  /**
   * Tests modulesInstalled() behavior with different inputs.
   *
   * @dataProvider providerModulesInstalled
   */
  public function testModulesInstalled(
    array $modules,
    bool $is_syncing,
    ?array $existingSettings,
    bool $customModuleAvailable,
    bool $expectsInstall,
    bool $expectsConfigChange,
  ): void {
    $moduleExtensionList = $this->createMock(ModuleExtensionList::class);
    $moduleInstaller = $this->createMock(ModuleInstallerInterface::class);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $config = $this->createMock(Config::class);

    $moduleExtensionList
      ->method('getList')
      ->willReturn($customModuleAvailable ? [
        'helfi_custom_test_content' => ['status' => 1],
      ] : []);

    if ($expectsInstall) {
      $moduleInstaller
        ->expects($this->once())
        ->method('install')
        ->with(['helfi_custom_test_content']);
    }
    else {
      $moduleInstaller
        ->expects($this->never())
        ->method('install');
    }

    if ($expectsConfigChange) {
      $configFactory
        ->expects($this->once())
        ->method('getEditable')
        ->with('block.block.announcements')
        ->willReturn($config);

      $config
        ->expects($this->once())
        ->method('get')
        ->with('settings')
        ->willReturn($existingSettings);

      $expectedSettings = $existingSettings ?? [];
      $expectedSettings['use_remote_entities'] = FALSE;

      $config
        ->expects($this->once())
        ->method('set')
        ->with('settings', $expectedSettings)
        ->willReturnSelf();

      $config
        ->expects($this->once())
        ->method('save');
    }
    else {
      $configFactory
        ->expects($this->never())
        ->method('getEditable');
    }

    $sut = new ModuleHooks($moduleExtensionList, $moduleInstaller, $configFactory);
    $sut->modulesInstalled($modules, $is_syncing);
  }

  /**
   * Data provider for testModulesInstalled().
   *
   * @return array[]
   *   The test cases.
   */
  public static function providerModulesInstalled(): array {
    return [
      'does nothing when syncing' => [
        'modules' => ['helfi_test_content'],
        'is_syncing' => TRUE,
        'existingSettings' => ['foo' => 'bar'],
        'customModuleAvailable' => TRUE,
        'expectsInstall' => FALSE,
        'expectsConfigChange' => FALSE,
      ],
      'does nothing when helfi_test_content not installed' => [
        'modules' => ['system'],
        'is_syncing' => FALSE,
        'existingSettings' => ['foo' => 'bar'],
        'customModuleAvailable' => TRUE,
        'expectsInstall' => FALSE,
        'expectsConfigChange' => FALSE,
      ],
      'installs custom module and updates config when available' => [
        'modules' => ['helfi_test_content'],
        'is_syncing' => FALSE,
        'existingSettings' => ['foo' => 'bar'],
        'customModuleAvailable' => TRUE,
        'expectsInstall' => TRUE,
        'expectsConfigChange' => TRUE,
      ],
      'skips custom module install but still updates config when not available' => [
        'modules' => ['helfi_test_content'],
        'is_syncing' => FALSE,
        'existingSettings' => NULL,
        'customModuleAvailable' => FALSE,
        'expectsInstall' => FALSE,
        'expectsConfigChange' => TRUE,
      ],
    ];
  }

}
