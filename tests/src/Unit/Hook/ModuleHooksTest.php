<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\Hook;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\helfi_platform_config\ClearSiteData;
use Drupal\helfi_platform_config\ConfigUpdate\ConfigUpdaterInterface;
use Drupal\helfi_platform_config\ConfigUpdate\ParagraphTypeUpdater;
use Drupal\helfi_platform_config\Hook\PlatformConfigHooks;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests the module hooks.
 *
 * @group helfi_platform_config
 */
final class ModuleHooksTest extends UnitTestCase {

  /**
   * The module handler mock.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  private ModuleHandlerInterface $moduleHandler;

  /**
   * The config updater mock.
   *
   * @var \Drupal\helfi_platform_config\ConfigUpdate\ConfigUpdaterInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  private ConfigUpdaterInterface $configUpdater;

  /**
   * The paragraph type updater mock.
   *
   * @var \Drupal\helfi_platform_config\ConfigUpdate\ParagraphTypeUpdater&\PHPUnit\Framework\MockObject\MockObject
   */
  private ParagraphTypeUpdater $paragraphTypeUpdater;

  /**
   * The clear site data mock.
   *
   * @var \Drupal\helfi_platform_config\ClearSiteData&\PHPUnit\Framework\MockObject\MockObject
   */
  private ClearSiteData $clearSiteData;

  /**
   * The system under test.
   */
  private PlatformConfigHooks $sut;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $this->configUpdater = $this->createMock(ConfigUpdaterInterface::class);
    $this->paragraphTypeUpdater = $this->createMock(ParagraphTypeUpdater::class);
    $this->clearSiteData = $this->createMock(ClearSiteData::class);
    $this->sut = new PlatformConfigHooks(
      $this->moduleHandler,
      $this->configUpdater,
      $this->paragraphTypeUpdater,
      $this->clearSiteData
    );
  }

  /**
   * Tests modulesInstalled() basic behavior with different inputs.
   */
  #[DataProvider('providerModulesInstalled')]
  public function testModulesInstalled(
    array $modules,
    bool $is_syncing,
    int $expectedInvokeCalls,
    int $expectedUpdatePermissionsCalls,
    bool $expectsParagraphUpdate,
  ): void {
    $this->moduleHandler
      ->method('moduleExists')
      ->with('locale')
      ->willReturn(FALSE);

    $this->moduleHandler
      ->expects($this->exactly($expectedInvokeCalls))
      ->method('invoke')
      ->with(
        $this->anything(),
        'platform_config_grant_permissions'
      );

    $this->configUpdater
      ->expects($this->exactly($expectedUpdatePermissionsCalls))
      ->method('updatePermissions');

    if ($expectsParagraphUpdate) {
      $this->paragraphTypeUpdater
        ->expects($this->once())
        ->method('updateParagraphTargetTypes');
    }
    else {
      $this->paragraphTypeUpdater
        ->expects($this->never())
        ->method('updateParagraphTargetTypes');
    }

    $this->sut->modulesInstalled($modules, $is_syncing);
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
    $modules = ['mod_a', 'mod_b'];

    $this->moduleHandler
      ->method('moduleExists')
      ->with('locale')
      ->willReturn(FALSE);

    $this->moduleHandler
      ->expects($this->exactly(2))
      ->method('invoke')
      ->willReturnMap([
        ['mod_a', 'platform_config_grant_permissions', ['perm a 1', 'perm a 2']],
        ['mod_b', 'platform_config_grant_permissions', NULL],
      ]);

    // Expect first call with the permissions array, second call with [].
    $this->configUpdater
      ->expects($this->exactly(2))
      ->method('updatePermissions');

    $this->paragraphTypeUpdater
      ->expects($this->once())
      ->method('updateParagraphTargetTypes');

    $this->sut->modulesInstalled($modules, FALSE);
  }

  /**
   * Tests cron() basic behavior.
   */
  public function testCron(): void {
    $this->clearSiteData
      ->expects($this->once())
      ->method('disableIfExpired');

    $this->sut->cron();
  }

}
