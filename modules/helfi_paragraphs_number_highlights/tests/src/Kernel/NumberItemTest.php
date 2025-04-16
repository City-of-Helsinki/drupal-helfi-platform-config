<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_number_highlights\Kernel;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\field\Kernel\FieldKernelTestBase;

/**
 * Tests the number numbers_item field type.
 */
class NumberItemTest extends FieldKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['helfi_paragraphs_number_highlights'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    FieldStorageConfig::create([
      'field_name' => 'field_test',
      'entity_type' => 'entity_test',
      'type' => 'numbers_item',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'field_test',
      'bundle' => 'entity_test',
      'settings' => [],
    ])->save();
  }

  /**
   * Tests using entity fields of the link field type.
   */
  public function testNumberItem(): void {
    // Create entity.
    $entity = EntityTest::create([
      'name' => $this->randomMachineName(),
    ]);

    $this->assertTrue($entity->field_test->isEmpty());

    $entity->field_test->number = '50';
    $entity->field_test->text = 'Half';

    $this->assertFalse($entity->field_test->isEmpty());
    $entity->save();

    // Verify that the field value is changed.
    $id = $entity->id();
    $entity = EntityTest::load($id);
    $this->assertInstanceOf(FieldItemListInterface::class, $entity->field_test);
    $this->assertInstanceOf(FieldItemInterface::class, $entity->field_test[0]);
    $this->assertEquals('50', $entity->field_test->number);
    $this->assertEquals('50', $entity->field_test[0]->number);
    $this->assertEquals('Half', $entity->field_test->text);
    $this->assertEquals('Half', $entity->field_test[0]->text);

    // Update only the entity name property to check if the link field data will
    // remain intact.
    $entity->name->value = $this->randomMachineName();
    $entity->save();
    $id = $entity->id();
    $entity = EntityTest::load($id);
    $this->assertEquals('50', $entity->field_test->number);
    $this->assertEquals('Half', $entity->field_test->text);

    // Verify changing the field value.
    $entity->field_test->number = '75';
    $entity->field_test->text = 'Three quarters';
    $this->assertEquals('75', $entity->field_test->number);
    $this->assertEquals('Three quarters', $entity->field_test->text);

    // Read changed entity and assert changed values.
    $entity->save();
    $entity = EntityTest::load($id);
    $this->assertEquals('75', $entity->field_test->number);
    $this->assertEquals('Three quarters', $entity->field_test->text);

    // Check that setting value NULL doesn't generate any error or
    // warning.
    $entity->field_test[0] = NULL;
    $this->assertNull($entity->field_test[0]->getValue());
  }

}
