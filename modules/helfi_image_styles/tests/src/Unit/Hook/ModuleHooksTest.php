<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_image_styles\Unit\Hook;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\helfi_image_styles\Hook\ModuleHooks;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests the module hooks.
 *
 * @group helfi_image_styles
 */
final class ModuleHooksTest extends UnitTestCase {

  /**
   * Tests modulePreinstall() behavior with different inputs.
   */
  #[DataProvider('providerModulePreinstall')]
  public function testModulePreinstall(
    string $module,
    bool $isSyncing,
    ?array $availableModules,
    bool $expectsInstall,
  ): void {
    $moduleExtensionList = $this->createMock(ModuleExtensionList::class);
    $moduleInstaller = $this->createMock(ModuleInstallerInterface::class);

    if ($availableModules !== NULL) {
      $moduleExtensionList
        ->method('getList')
        ->willReturn($availableModules);
    }

    if ($expectsInstall) {
      $moduleInstaller
        ->expects($this->once())
        ->method('install')
        ->with(['imagemagick']);
    }
    else {
      $moduleInstaller
        ->expects($this->never())
        ->method('install');
    }

    $sut = new ModuleHooks($moduleExtensionList, $moduleInstaller);
    $sut->modulePreinstall($module, $isSyncing);
  }

  /**
   * Data provider for testModulePreinstall().
   *
   * @return array[]
   *   The test cases.
   */
  public static function providerModulePreinstall(): array {
    return [
      'installs imagemagick when available and not syncing' => [
        'module' => 'helfi_image_styles',
        'isSyncing' => FALSE,
        'availableModules' => [
          'imagemagick' => ['status' => 1],
        ],
        'expectsInstall' => TRUE,
      ],
      'does nothing when different module' => [
        'module' => 'other_module',
        'isSyncing' => FALSE,
        'availableModules' => NULL,
        'expectsInstall' => FALSE,
      ],
      'does nothing when syncing' => [
        'module' => 'helfi_image_styles',
        'isSyncing' => TRUE,
        'availableModules' => NULL,
        'expectsInstall' => FALSE,
      ],
      'does nothing when imagemagick not available' => [
        'module' => 'helfi_image_styles',
        'isSyncing' => FALSE,
        'availableModules' => [],
        'expectsInstall' => FALSE,
      ],
    ];
  }

}
