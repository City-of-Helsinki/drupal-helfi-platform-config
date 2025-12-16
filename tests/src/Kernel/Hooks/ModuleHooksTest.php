<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\Hooks;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\helfi_platform_config\ConfigUpdate\ConfigUpdaterInterface;
use Drupal\helfi_platform_config\ConfigUpdate\ParagraphTypeUpdater;
use Drupal\helfi_platform_config\Hook\ModuleHooks;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests hook_modules_installed() implementation.
 */
final class ModuleHooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'locale',
    'language',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();
    $this->installConfig(['locale', 'language']);
  }

  /**
   * Tests that permissions and paragraph targets are updated
   * when modules are installed.
   */
  public function testModulesInstalledUpdatesEverything(): void {
    // Tests that permissions and paragraph targets are updated
    // when modules are installed.
    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $configUpdater = $this->createMock(ConfigUpdaterInterface::class);
    $paragraphTypeUpdater = $this->createMock(ParagraphTypeUpdater::class);

    $modules = ['module_a', 'module_b'];
    $expectedPermissions = [['access content', 'administer nodes'], []];

    $moduleHandler->method('moduleExists')
      ->with('locale')
      ->willReturn(TRUE);

    $moduleHandler->expects($this->exactly(2))
      ->method('invoke')
      ->willReturnCallback(
        static function (string $module) {
          return $module === 'module_a'
            ? ['access content', 'administer nodes']
            : [];
        }
      );

    // Assertions
    $configUpdater->expects($this->exactly(2))
      ->method('updatePermissions')
      ->willReturnCallback(
        static function (array $permissions) use (&$receivedPermissions): void {
          $receivedPermissions[] = $permissions;
        }
      );

    $paragraphTypeUpdater->expects($this->once())
      ->method('updateParagraphTargetTypes');

    // Execute
    $sut = new ModuleHooks(
      $moduleHandler,
      $configUpdater,
      $paragraphTypeUpdater,
    );
    $sut->modulesInstalled($modules, FALSE);

    // Verify
    $this->assertSame($expectedPermissions, $receivedPermissions);
  }

}
