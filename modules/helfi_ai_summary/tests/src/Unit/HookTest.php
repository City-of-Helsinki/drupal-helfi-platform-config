<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai_summary\Unit;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @covers ::helfi_ai_summary_entity_base_field_info
 * @group helfi_ai_summary
 */
class HookTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  public static function setUpBeforeClass(): void {
    parent::setUpBeforeClass();
    require_once __DIR__ . '/../../../helfi_ai_summary.module';
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    parent::tearDown();
    $container = $this->createMock(ContainerInterface::class);
    \Drupal::setContainer($container);
  }

  /**
   * @covers ::helfi_ai_summary_entity_base_field_info
   */
  public function testReturnsEmptyArrayForNonNodeEntityType(): void {
    $entityType = $this->prophesize(EntityTypeInterface::class);
    $entityType->id()->willReturn('user');

    $result = helfi_ai_summary_entity_base_field_info($entityType->reveal());
    $this->assertSame([], $result);
  }

  /**
   * @covers ::helfi_ai_summary_entity_base_field_info
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

    $result = helfi_ai_summary_entity_base_field_info($entityType->reveal());

    $this->assertArrayHasKey('field_ai_summary', $result);
    $this->assertInstanceOf(BaseFieldDefinition::class, $result['field_ai_summary']);
    $this->assertTrue($result['field_ai_summary']->isRevisionable());
    $this->assertTrue($result['field_ai_summary']->isTranslatable());
  }

}
