<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Unit\Hook;

use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\helfi_ai\Hook\ModuleHooks;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * Tests the ai_summary field installation for tpr_service.
 */
#[Group('helfi_ai')]
#[CoversClass(ModuleHooks::class)]
class ModuleHooksTest extends UnitTestCase {

  /**
   * The entity definition update manager.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy<\Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface>
   */
  private ObjectProphecy $entityDefinitionUpdateManager;

  /**
   * The entity field manager.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy<\Drupal\Core\Entity\EntityFieldManagerInterface>
   */
  private ObjectProphecy $entityFieldManager;

  /**
   * The entity type manager.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy<\Drupal\Core\Entity\EntityTypeManagerInterface>
   */
  private ObjectProphecy $entityTypeManager;

  /**
   * The tpr_service form display.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy<\Drupal\Core\Entity\Display\EntityFormDisplayInterface>
   */
  private ObjectProphecy $formDisplay;

  /**
   * The tpr_service view display.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy<\Drupal\Core\Entity\Display\EntityViewDisplayInterface>
   */
  private ObjectProphecy $viewDisplay;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityDefinitionUpdateManager = $this->prophesize(EntityDefinitionUpdateManagerInterface::class);
    $this->entityFieldManager = $this->prophesize(EntityFieldManagerInterface::class);
    $this->entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $this->formDisplay = $this->prophesize(EntityFormDisplayInterface::class);
    $this->viewDisplay = $this->prophesize(EntityViewDisplayInterface::class);
  }

  /**
   * Sets up a site that has the tpr_service entity type and the field.
   *
   * @return \Drupal\Core\Field\FieldStorageDefinitionInterface
   *   The ai_summary field definition.
   */
  private function setUpTprService(): FieldStorageDefinitionInterface {
    $definition = $this->prophesize(FieldStorageDefinitionInterface::class)->reveal();

    $this->entityTypeManager->hasDefinition('tpr_service')->willReturn(TRUE);
    $this->entityFieldManager->getFieldStorageDefinitions('tpr_service')
      ->willReturn(['ai_summary' => $definition]);

    $displays = [
      'entity_form_display' => $this->formDisplay,
      'entity_view_display' => $this->viewDisplay,
    ];

    foreach ($displays as $entityTypeId => $display) {
      $storage = $this->prophesize(ConfigEntityStorageInterface::class);
      $storage->load('tpr_service.tpr_service.default')->willReturn($display->reveal());
      $this->entityTypeManager->getStorage($entityTypeId)->willReturn($storage->reveal());
    }

    return $definition;
  }

  /**
   * Builds the hooks object under test with mocked dependencies.
   *
   * @return \Drupal\helfi_ai\Hook\ModuleHooks
   *   The hooks object.
   */
  private function createHooks(): ModuleHooks {
    return new ModuleHooks(
      $this->entityDefinitionUpdateManager->reveal(),
      $this->entityFieldManager->reveal(),
      $this->entityTypeManager->reveal(),
    );
  }

  /**
   * Tests that a missing field is installed for helfi_ai.
   */
  public function testInstallsMissingFieldStorage(): void {
    $definition = $this->setUpTprService();
    $this->entityDefinitionUpdateManager->getFieldStorageDefinition('ai_summary', 'tpr_service')
      ->willReturn(NULL);

    $this->entityDefinitionUpdateManager
      ->installFieldStorageDefinition('ai_summary', 'tpr_service', 'helfi_ai', $definition)
      ->shouldBeCalled();
    $this->entityDefinitionUpdateManager->updateFieldStorageDefinition($definition)
      ->shouldNotBeCalled();

    $this->createHooks()->installAiSummaryField();
  }

  /**
   * Tests that the field installed by helfi_tpr_config is taken over.
   */
  public function testTakesOverFieldStorageInstalledByTprConfig(): void {
    $definition = $this->setUpTprService();

    $installed = $this->prophesize(FieldStorageDefinitionInterface::class);
    $installed->getProvider()->willReturn('helfi_tpr_config');
    $this->entityDefinitionUpdateManager->getFieldStorageDefinition('ai_summary', 'tpr_service')
      ->willReturn($installed->reveal());

    $this->entityDefinitionUpdateManager->updateFieldStorageDefinition($definition)
      ->shouldBeCalled();
    $this->entityDefinitionUpdateManager
      ->installFieldStorageDefinition('ai_summary', 'tpr_service', 'helfi_ai', $definition)
      ->shouldNotBeCalled();

    $this->createHooks()->installAiSummaryField();
  }

  /**
   * Tests that a field already owned by helfi_ai is left alone.
   */
  public function testKeepsFieldStorageInstalledByHelfiAi(): void {
    $definition = $this->setUpTprService();

    $installed = $this->prophesize(FieldStorageDefinitionInterface::class);
    $installed->getProvider()->willReturn('helfi_ai');
    $this->entityDefinitionUpdateManager->getFieldStorageDefinition('ai_summary', 'tpr_service')
      ->willReturn($installed->reveal());

    $this->entityDefinitionUpdateManager->updateFieldStorageDefinition($definition)
      ->shouldNotBeCalled();
    $this->entityDefinitionUpdateManager
      ->installFieldStorageDefinition('ai_summary', 'tpr_service', 'helfi_ai', $definition)
      ->shouldNotBeCalled();

    $this->createHooks()->installAiSummaryField();
  }

  /**
   * Tests that both displays are saved for the presave hooks.
   */
  public function testSavesFormAndViewDisplays(): void {
    $this->setUpTprService();
    $this->entityDefinitionUpdateManager->getFieldStorageDefinition('ai_summary', 'tpr_service')
      ->willReturn(NULL);

    $this->formDisplay->save()->shouldBeCalledOnce();
    $this->viewDisplay->save()->shouldBeCalledOnce();

    $this->createHooks()->installAiSummaryField();
  }

  /**
   * Tests that nothing is installed without the tpr_service entity type.
   */
  public function testSkipsSiteWithoutTprService(): void {
    $this->entityTypeManager->hasDefinition('tpr_service')->willReturn(FALSE);
    $this->entityFieldManager->getFieldStorageDefinitions('tpr_service')->shouldNotBeCalled();

    $this->createHooks()->installAiSummaryField();
  }

  /**
   * Tests that nothing is installed when the field is not defined.
   */
  public function testSkipsUndefinedField(): void {
    $this->entityTypeManager->hasDefinition('tpr_service')->willReturn(TRUE);
    $this->entityFieldManager->getFieldStorageDefinitions('tpr_service')->willReturn([]);
    $this->entityDefinitionUpdateManager->getFieldStorageDefinition('ai_summary', 'tpr_service')
      ->shouldNotBeCalled();

    $this->createHooks()->installAiSummaryField();
  }

  /**
   * Tests that installing either module installs the field.
   */
  public function testInstallsFieldForRelatedModules(): void {
    $this->entityTypeManager->hasDefinition('tpr_service')
      ->willReturn(FALSE)
      ->shouldBeCalledTimes(3);

    foreach ([['helfi_ai'], ['helfi_tpr_config'], ['node', 'helfi_ai']] as $modules) {
      $this->createHooks()->modulesInstalled($modules, FALSE);
    }
  }

  /**
   * Tests that unrelated module installations are ignored.
   */
  public function testSkipsUnrelatedModules(): void {
    $this->entityTypeManager->hasDefinition('tpr_service')->shouldNotBeCalled();

    $this->createHooks()->modulesInstalled(['node', 'helfi_tpr'], FALSE);
  }

  /**
   * Tests that configuration import is ignored.
   */
  public function testSkipsSyncingInstall(): void {
    $this->entityTypeManager->hasDefinition('tpr_service')->shouldNotBeCalled();

    $this->createHooks()->modulesInstalled(['helfi_ai'], TRUE);
  }

}
