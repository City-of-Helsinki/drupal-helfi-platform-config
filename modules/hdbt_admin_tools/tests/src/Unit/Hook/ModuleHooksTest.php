<?php

declare(strict_types=1);

namespace Drupal\Tests\hdbt_admin_tools\Unit\Hook;

use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\hdbt_admin_tools\Hook\ModuleHooks;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the module hooks.
 *
 * @group hdbt_admin_tools
 */
final class ModuleHooksTest extends UnitTestCase {

  /**
   * Tests modulesInstalled() behavior with different inputs.
   *
   * @covers ::modulesInstalled
   * @dataProvider providerModulesInstalled
   */
  public function testModulesInstalled(
    array $modules,
    bool $is_syncing,
    bool $hasFieldDefinitions,
    int $expectedInstallCalls,
  ): void {
    $entityDefinitionUpdateManager = $this->createMock(EntityDefinitionUpdateManagerInterface::class);
    $entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

    // Mock entity type definitions
    $entityTypeManager
      ->method('hasDefinition')
      ->willReturnMap([
        ['node', true],
        ['tpr_unit', true],
        ['tpr_service', true],
      ]);

    if ($hasFieldDefinitions) {
      $colorPaletteDefinition = $this->createMock(FieldStorageDefinitionInterface::class);
      $hideSidebarDefinition = $this->createMock(FieldStorageDefinitionInterface::class);

      $entityFieldManager
        ->method('getFieldDefinitions')
        ->willReturnCallback(function ($entityType) use ($colorPaletteDefinition, $hideSidebarDefinition) {
          return [
            'color_palette' => $colorPaletteDefinition,
            'hide_sidebar_navigation' => $hideSidebarDefinition,
          ];
        });
    }
    else {
      $entityFieldManager
        ->method('getFieldDefinitions')
        ->willReturn([]);
    }

    $entityDefinitionUpdateManager
      ->expects($this->exactly($expectedInstallCalls))
      ->method('installFieldStorageDefinition');

    $sut = new ModuleHooks($entityDefinitionUpdateManager, $entityFieldManager, $entityTypeManager);
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
        'modules' => ['helfi_node_page'],
        'is_syncing' => TRUE,
        'hasFieldDefinitions' => TRUE,
        'expectedInstallCalls' => 0,
      ],
      'does nothing when no matching modules installed' => [
        'modules' => ['system'],
        'is_syncing' => FALSE,
        'hasFieldDefinitions' => TRUE,
        'expectedInstallCalls' => 0,
      ],
      'installs fields when matching module installed and definitions exist' => [
        'modules' => ['helfi_node_page'],
        'is_syncing' => FALSE,
        'hasFieldDefinitions' => TRUE,
        'expectedInstallCalls' => 6,
      ],
      'skips install when matching module installed but definitions missing' => [
        'modules' => ['helfi_node_page'],
        'is_syncing' => FALSE,
        'hasFieldDefinitions' => FALSE,
        'expectedInstallCalls' => 0,
      ],
    ];
  }

}
