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
   * Builds a form display that has no ai_summary field yet.
   *
   * @param string $id
   *   The display id.
   *
   * @return \Prophecy\Prophecy\ObjectProphecy<EntityFormDisplayInterface>
   *   The form display.
   */
  private function prophesizeFormDisplay(string $id): ObjectProphecy {
    $display = $this->prophesize(EntityFormDisplayInterface::class);
    $display->id()->willReturn($id);
    $display->isSyncing()->willReturn(FALSE);
    $display->getComponent('ai_summary')->willReturn(NULL);

    return $display;
  }

  /**
   * Builds a view display that has no ai_summary field yet.
   *
   * @param string $id
   *   The display id.
   *
   * @return \Prophecy\Prophecy\ObjectProphecy<EntityViewDisplayInterface>
   *   The view display.
   */
  private function prophesizeViewDisplay(string $id): ObjectProphecy {
    $display = $this->prophesize(EntityViewDisplayInterface::class);
    $display->id()->willReturn($id);
    $display->isSyncing()->willReturn(FALSE);
    $display->getComponent('ai_summary')->willReturn(NULL);

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
   * Test that the widget is placed after the lead-in on the basic page form.
   */
  public function testAddsWidgetAfterLeadInToPageFormDisplay(): void {
    $display = $this->prophesizeFormDisplay('node.page.default');
    $display->getComponent('field_lead_in')->willReturn(['weight' => 13]);
    $display->getComponents()->willReturn([
      'title' => ['weight' => 1],
      'field_lead_in' => ['weight' => 13],
      'field_content' => ['weight' => 14],
    ]);
    $display->setComponent('field_content', ['weight' => 15])->shouldBeCalled();
    $display->setComponent('title', Argument::any())->shouldNotBeCalled();
    $display->setComponent('field_lead_in', Argument::any())->shouldNotBeCalled();
    $display->setComponent('ai_summary', [
      'type' => 'ai_summary',
      'region' => 'content',
      'weight' => 14,
    ])->shouldBeCalled();

    (new EntityHooks())->entityFormDisplayPresave($display->reveal());
  }

  /**
   * Test that the formatter is placed after the lead-in on the basic page view.
   */
  public function testAddsFormatterAfterLeadInToPageViewDisplay(): void {
    $display = $this->prophesizeViewDisplay('node.page.default');
    $display->getComponent('field_lead_in')->willReturn(['weight' => 1]);
    $display->getComponents()->willReturn([
      'links' => ['weight' => 0],
      'field_lead_in' => ['weight' => 1],
      'toc_enabled' => ['weight' => 2],
      'field_content' => ['weight' => 3],
    ]);
    $display->setComponent('toc_enabled', ['weight' => 3])->shouldBeCalled();
    $display->setComponent('field_content', ['weight' => 4])->shouldBeCalled();
    $display->setComponent('links', Argument::any())->shouldNotBeCalled();
    $display->setComponent('field_lead_in', Argument::any())->shouldNotBeCalled();
    $display->setComponent('ai_summary', [
      'type' => 'text_default',
      'label' => 'hidden',
      'region' => 'content',
      'weight' => 2,
    ])->shouldBeCalled();

    (new EntityHooks())->entityViewDisplayPresave($display->reveal());
  }

  /**
   * Test that the widget is placed last when the lead-in is missing.
   */
  public function testAddsWidgetLastWithoutLeadIn(): void {
    $display = $this->prophesizeFormDisplay('node.page.default');
    $display->getComponent('field_lead_in')->willReturn(NULL);
    $display->setComponent('ai_summary', [
      'type' => 'ai_summary',
      'region' => 'content',
    ])->shouldBeCalled();

    (new EntityHooks())->entityFormDisplayPresave($display->reveal());
  }

  /**
   * Test that the widget is added to the tpr_service form display.
   */
  public function testAddsWidgetToTprServiceFormDisplay(): void {
    $display = $this->prophesizeFormDisplay('tpr_service.tpr_service.default');
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
    $display = $this->prophesizeViewDisplay('tpr_service.tpr_service.default');
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
    $formDisplay = $this->prophesizeFormDisplay('node.landing_page.default');
    $formDisplay->setComponent(Argument::cetera())->shouldNotBeCalled();

    $viewDisplay = $this->prophesizeViewDisplay('tpr_service.tpr_service.teaser');
    $viewDisplay->setComponent(Argument::cetera())->shouldNotBeCalled();

    $hooks = new EntityHooks();
    $hooks->entityFormDisplayPresave($formDisplay->reveal());
    $hooks->entityViewDisplayPresave($viewDisplay->reveal());
  }

  /**
   * Test that a display being imported is left alone.
   */
  public function testIgnoresSyncingDisplay(): void {
    $display = $this->prophesizeFormDisplay('node.page.default');
    $display->isSyncing()->willReturn(TRUE);
    $display->setComponent(Argument::cetera())->shouldNotBeCalled();

    (new EntityHooks())->entityFormDisplayPresave($display->reveal());
  }

  /**
   * Test that an existing component is not overridden.
   */
  public function testKeepsExistingComponent(): void {
    $display = $this->prophesizeFormDisplay('node.page.default');
    $display->getComponent('ai_summary')->willReturn(['type' => 'string_textarea']);
    $display->setComponent(Argument::cetera())->shouldNotBeCalled();

    (new EntityHooks())->entityFormDisplayPresave($display->reveal());
  }

}
