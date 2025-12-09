<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\Hook;

use DG\BypassFinals;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\helfi_platform_config\ConfigUpdate\ConfigUpdater;
use Drupal\helfi_platform_config\ConfigUpdate\ParagraphTypeUpdater;
use Drupal\helfi_platform_config\Hook\ModuleHooks;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;

/**
 * Tests the module hooks.
 *
 * @group helfi_platform_config
 */
final class ModuleHooksTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    BypassFinals::enable();
  }

  /**
   * Tests modulesInstalled() basic behavior with different inputs.
   *
   * @dataProvider providerModulesInstalled
   */
  public function testModulesInstalled(
    array $modules,
    bool $is_syncing,
    int $expectedInvokeCalls,
    int $expectedUpdatePermissionsCalls,
    bool $expectsParagraphUpdate,
  ): void {
    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);

    $configUpdater = $this->prophesize(ConfigUpdater::class);
    $paragraphTypeUpdater = $this->createMock(ParagraphTypeUpdater::class);

    $moduleHandler
      ->method('moduleExists')
      ->with('locale')
      ->willReturn(FALSE);

    $moduleHandler
      ->expects($this->exactly($expectedInvokeCalls))
      ->method('invoke')
      ->with(
        $this->isType('string'),
        'platform_config_grant_permissions'
      );

    $configUpdater
      ->updatePermissions(Argument::type('array'))
      ->shouldBeCalledTimes($expectedUpdatePermissionsCalls);

    if ($expectsParagraphUpdate) {
      $paragraphTypeUpdater
        ->expects($this->once())
        ->method('updateParagraphTargetTypes');
    }
    else {
      $paragraphTypeUpdater
        ->expects($this->never())
        ->method('updateParagraphTargetTypes');
    }

    $sut = new ModuleHooks(
      $moduleHandler,
      $configUpdater->reveal(),
      $entityFieldManager,
      $paragraphTypeUpdater
    );

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
        'modules' => ['example_module'],
        'is_syncing' => TRUE,
        'expectedInvokeCalls' => 0,
        'expectedUpdatePermissionsCalls' => 0,
        'expectsParagraphUpdate' => FALSE,
      ],
      'no modules, not syncing' => [
        'modules' => [],
        'is_syncing' => FALSE,
        'expectedInvokeCalls' => 0,
        'expectedUpdatePermissionsCalls' => 0,
        'expectsParagraphUpdate' => TRUE,
      ],
      'multiple modules, not syncing' => [
        'modules' => ['mod_a', 'mod_b'],
        'is_syncing' => FALSE,
        'expectedInvokeCalls' => 2,
        'expectedUpdatePermissionsCalls' => 2,
        'expectsParagraphUpdate' => TRUE,
      ],
    ];
  }

  /**
   * Tests that permissions returned by invoke() are passed to ConfigUpdater.
   */
  public function testModulesInstalledPassesPermissionsToConfigUpdater(): void {
    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);

    $configUpdater = $this->prophesize(ConfigUpdater::class);
    $paragraphTypeUpdater = $this->createMock(ParagraphTypeUpdater::class);

    $modules = ['mod_a', 'mod_b'];

    $moduleHandler
      ->method('moduleExists')
      ->with('locale')
      ->willReturn(FALSE);

    $moduleHandler
      ->expects($this->exactly(2))
      ->method('invoke')
      ->willReturnMap([
        ['mod_a', 'platform_config_grant_permissions', ['perm a 1', 'perm a 2']],
        ['mod_b', 'platform_config_grant_permissions', NULL],
      ]);

    // Expect first call with the permissions array, second call with [].
    $configUpdater
      ->updatePermissions(['perm a 1', 'perm a 2'])
      ->shouldBeCalled();
    $configUpdater
      ->updatePermissions([])
      ->shouldBeCalled();

    $paragraphTypeUpdater
      ->expects($this->once())
      ->method('updateParagraphTargetTypes');

    $sut = new ModuleHooks(
      $moduleHandler,
      $configUpdater->reveal(),
      $entityFieldManager,
      $paragraphTypeUpdater
    );

    $sut->modulesInstalled($modules, FALSE);
  }

}
