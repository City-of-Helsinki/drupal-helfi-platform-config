<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Unit;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\helfi_ai\Hook\EntityHooks;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
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
   * Test that there is no ai_summary for non node entity types.
   */
  public function testReturnsEmptyArrayForNonNodeEntityType(): void {
    $entityType = $this->prophesize(EntityTypeInterface::class);
    $entityType->id()->willReturn('user');

    $hooks = new EntityHooks();
    $hooks->setStringTranslation($this->getStringTranslationStub());

    $result = $hooks->entityBaseFieldInfo($entityType->reveal());
    $this->assertSame([], $result);
  }

  /**
   * Test that the hook adds the ai_summary base field to node entities.
   */
  public function testReturnsFieldDefinitionForNodeEntityType(): void {
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

    $entityType = $this->prophesize(EntityTypeInterface::class);
    $entityType->id()->willReturn('node');

    $hooks = new EntityHooks();
    $hooks->setStringTranslation($this->getStringTranslationStub());

    $result = $hooks->entityBaseFieldInfo($entityType->reveal());

    $this->assertArrayHasKey('ai_summary', $result);
    $this->assertInstanceOf(BaseFieldDefinition::class, $result['ai_summary']);
    $this->assertTrue($result['ai_summary']->isRevisionable());
    $this->assertTrue($result['ai_summary']->isTranslatable());
  }

}
