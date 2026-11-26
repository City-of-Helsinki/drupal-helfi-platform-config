<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_test_content\Unit\Hook;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_test_content\Hook\ModuleHooks;
use Drupal\Tests\UnitTestCase;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the module hooks.
 */
#[Group('helfi_test_content')]
final class ModuleHooksTest extends UnitTestCase {

  use EnvironmentResolverTrait;

  /**
   * Tests modulesInstalled() behavior with different inputs.
   */
  #[DataProvider('providerModulesInstalled')]
  public function testModulesInstalled(
    array $modules,
    bool $is_syncing,
    ?array $existingSettings,
    bool $customModuleAvailable,
    bool $expectsInstall,
    bool $expectsConfigChange,
    string $environment = 'local',
  ): void {
    $moduleExtensionList = $this->createMock(ModuleExtensionList::class);
    $moduleInstaller = $this->createMock(ModuleInstallerInterface::class);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $config = $this->createMock(Config::class);
    $calls = [];
    $expectedSettings = $existingSettings ?? [];
    $expectedSettings['use_remote_entities'] = TRUE;

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

      $config
        ->expects($this->exactly(2))
        ->method('set')
        ->willReturnCallback(function (string $key, mixed $value) use (&$calls, $config) {
          $calls[$key] = $value;
          return $config;
        });

      $config
        ->expects($this->once())
        ->method('save');
    }
    else {
      $configFactory
        ->expects($this->never())
        ->method('getEditable');
    }

    $sut = new ModuleHooks($moduleExtensionList, $moduleInstaller, $configFactory, $this->getEnvironmentResolver(Project::ETUSIVU, EnvironmentEnum::from($environment)));
    $sut->modulesInstalled($modules, $is_syncing);

    if ($expectsConfigChange) {
      $this->assertSame($expectedSettings, $calls['settings'], 'Remote announcements are enabled.');
      $this->assertSame([
        'id' => 'request_path',
        'negate' => FALSE,
        'pages' => '/dc-components/dc-announcements',
      ], $calls['visibility.request_path'], 'Announcements are shown only on the announcements test page.');
    }
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
      'does nothing in production' => [
        'modules' => ['helfi_test_content'],
        'is_syncing' => FALSE,
        'existingSettings' => ['foo' => 'bar'],
        'customModuleAvailable' => TRUE,
        'expectsInstall' => FALSE,
        'expectsConfigChange' => FALSE,
        'environment' => 'prod',
      ],
    ];
  }

}
