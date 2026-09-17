<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Unit;

use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\helfi_ai\Hook\EntityHooks;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tests entity hooks for helfi_ai module.
 */
#[Group('helfi_ai')]
class HookTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    parent::tearDown();
    $container = $this->createMock(ContainerInterface::class);
    \Drupal::setContainer($container);
  }

  /**
   * Sets up a container that can build field definitions.
   */
  private function setUpFieldTypeContainer(): void {
    $fieldTypeManager = $this->createMock(FieldTypePluginManagerInterface::class);
    $fieldTypeManager->method('getDefaultStorageSettings')->willReturn([]);
    $fieldTypeManager->method('getDefaultFieldSettings')->willReturn([]);

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')->willReturnCallback(
      function (string $id) use ($fieldTypeManager): object {
        if ($id === 'plugin.manager.field.field_type') {
          return $fieldTypeManager;
        }
        if ($id === 'string_translation') {
          return $this->getStringTranslationStub();
        }
        throw new \RuntimeException('Unexpected service: ' . $id);
      }
    );
    \Drupal::setContainer($container);
  }

  /**
   * Builds a display that has no ai_summary field yet.
   *
   * @param string $id
   *   The display id.
   * @param string $class
   *   The display interface to prophesize.
   *
   * @return \Prophecy\Prophecy\ObjectProphecy
   *   The display.
   */
  private function prophesizeDisplay(string $id, string $class): ObjectProphecy {
    $display = $this->prophesize($class);
    $display->id()->willReturn($id);
    $display->isSyncing()->willReturn(FALSE);
    $display->getComponent('ai_summary')->willReturn(NULL);
    $display->get('hidden')->willReturn([]);

    return $display;
  }

  /**
   * Test that there is no ai_summary for unsupported entity types.
   */
  public function testReturnsEmptyArrayForUnsupportedEntityType(): void {
    $entityType = $this->prophesize(EntityTypeInterface::class);
    $entityType->id()->willReturn('user');

    $hooks = new EntityHooks();

    $result = $hooks->entityBaseFieldInfo($entityType->reveal());
    $this->assertSame([], $result);
  }

  /**
   * Test that the hook adds the ai_summary base field to node entities.
   */
  public function testReturnsFieldDefinitionForNodeEntityType(): void {
    $this->setUpFieldTypeContainer();

    $entityType = $this->prophesize(EntityTypeInterface::class);
    $entityType->id()->willReturn('node');

    $hooks = new EntityHooks();

    $result = $hooks->entityBaseFieldInfo($entityType->reveal());

    $this->assertArrayHasKey('ai_summary', $result);
    $this->assertInstanceOf(BaseFieldDefinition::class, $result['ai_summary']);
    $this->assertTrue($result['ai_summary']->isRevisionable());
    $this->assertTrue($result['ai_summary']->isTranslatable());
  }

  /**
   * Test that the hook adds the ai_summary base field to tpr_service entities.
   */
  public function testReturnsFieldDefinitionForTprServiceEntityType(): void {
    $this->setUpFieldTypeContainer();

    $entityType = $this->prophesize(EntityTypeInterface::class);
    $entityType->id()->willReturn('tpr_service');

    $hooks = new EntityHooks();

    $result = $hooks->entityBaseFieldInfo($entityType->reveal());

    $this->assertArrayHasKey('ai_summary', $result);
    $this->assertInstanceOf(BaseFieldDefinition::class, $result['ai_summary']);
  }

  /**
   * Test that the widget is added to the tpr_service form display.
   */
  public function testAddsWidgetToTprServiceFormDisplay(): void {
    $display = $this->prophesizeDisplay('tpr_service.tpr_service.default', EntityFormDisplayInterface::class);
    $display->setComponent('ai_summary', [
      'type' => 'ai_summary',
      'weight' => 7,
      'region' => 'content',
    ])->shouldBeCalled();

    (new EntityHooks())->entityFormDisplayPresave($display->reveal());
  }

  /**
   * Test that the formatter is added to the tpr_service view display.
   */
  public function testAddsFormatterToTprServiceViewDisplay(): void {
    $display = $this->prophesizeDisplay('tpr_service.tpr_service.default', EntityViewDisplayInterface::class);
    $display->setComponent('ai_summary', [
      'type' => 'text_default',
      'label' => 'hidden',
      'weight' => 4,
      'region' => 'content',
    ])->shouldBeCalled();

    (new EntityHooks())->entityViewDisplayPresave($display->reveal());
  }

  /**
   * Test that other displays are left alone.
   */
  public function testIgnoresOtherDisplays(): void {
    $formDisplay = $this->prophesizeDisplay('node.page.default', EntityFormDisplayInterface::class);
    $formDisplay->setComponent(Argument::cetera())->shouldNotBeCalled();

    $viewDisplay = $this->prophesizeDisplay('tpr_service.tpr_service.teaser', EntityViewDisplayInterface::class);
    $viewDisplay->setComponent(Argument::cetera())->shouldNotBeCalled();

    $hooks = new EntityHooks();
    $hooks->entityFormDisplayPresave($formDisplay->reveal());
    $hooks->entityViewDisplayPresave($viewDisplay->reveal());
  }

  /**
   * Test that a display being imported is left alone.
   */
  public function testIgnoresSyncingDisplay(): void {
    $display = $this->prophesizeDisplay('tpr_service.tpr_service.default', EntityFormDisplayInterface::class);
    $display->isSyncing()->willReturn(TRUE);
    $display->setComponent(Argument::cetera())->shouldNotBeCalled();

    (new EntityHooks())->entityFormDisplayPresave($display->reveal());
  }

  /**
   * Test that an existing component is not overridden.
   */
  public function testKeepsExistingComponent(): void {
    $display = $this->prophesizeDisplay('tpr_service.tpr_service.default', EntityFormDisplayInterface::class);
    $display->getComponent('ai_summary')->willReturn(['type' => 'string_textarea']);
    $display->setComponent(Argument::cetera())->shouldNotBeCalled();

    (new EntityHooks())->entityFormDisplayPresave($display->reveal());
  }

  /**
   * Test that a field hidden by an editor stays hidden.
   */
  public function testKeepsFieldHiddenByEditor(): void {
    $display = $this->prophesizeDisplay('tpr_service.tpr_service.default', EntityFormDisplayInterface::class);
    $display->get('hidden')->willReturn(['ai_summary' => TRUE]);
    $display->setComponent(Argument::cetera())->shouldNotBeCalled();

    (new EntityHooks())->entityFormDisplayPresave($display->reveal());
  }

}
