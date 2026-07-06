<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_node_announcement\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormState;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\helfi_node_announcement\Hook\FieldWidgetHooks;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests the field widget single element form alter hook.
 *
 * @group helfi_node_announcement
 */
class FieldWidgetHooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'node',
    'select2',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');

    NodeType::create(['type' => 'announcement', 'name' => 'Announcement'])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_reference',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'node'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_reference',
      'entity_type' => 'node',
      'bundle' => 'announcement',
    ])->save();

    EntityFormDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'announcement',
      'mode' => 'default',
      'status' => TRUE,
    ])->setComponent('field_reference', ['type' => 'select2_entity_reference'])->save();
  }

  /**
   * Tests that the drag message is stripped from the select2 widget.
   */
  public function testDragMessageRemoved(): void {
    $node = Node::create(['type' => 'announcement', 'title' => 'Test Announcement']);

    $display = EntityFormDisplay::collectRenderDisplay($node, 'default');
    $context = [
      'widget' => $display->getRenderer('field_reference'),
      'items' => $node->get('field_reference'),
    ];
    $element = [
      '#description' => [
        '#theme' => 'item_list',
        '#items' => ['My own description.', 'Drag to re-order items.'],
      ],
    ];

    $hooks = new FieldWidgetHooks();
    $hooks->fieldWidgetFormAlter($element, new FormState(), $context);
    $this->assertSame('My own description.', $element['#description']);
  }

}
