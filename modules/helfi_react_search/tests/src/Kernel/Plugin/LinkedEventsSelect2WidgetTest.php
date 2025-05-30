<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_react_search\Kernel\Plugin;

use Drupal\Core\Form\FormState;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\helfi_react_search\Plugin\Field\FieldWidget\LinkedEventsSelect2Widget;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests linked events widget.
 */
class LinkedEventsSelect2WidgetTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'helfi_api_base',
    'helfi_react_search',
    'entity_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test');

    FieldStorageConfig::create([
      'field_name' => 'field_test',
      'entity_type' => 'entity_test',
      'type' => 'string',
      'cardinality' => 1,
      'settings' => [
        'max_length' => 255,
      ],
    ])->save();
  }

  /**
   * Tests building widget.
   */
  public function testWidget() {
    $fieldDefinition = FieldConfig::create([
      'field_name' => 'field_test',
      'label' => 'A test field',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ]);
    $fieldDefinition->save();

    $form = [];
    $element = [
      '#required' => FALSE,
    ];
    $entity = EntityTest::create();
    $field = $entity->get('field_test');

    // Options are initially empty.
    $widget = LinkedEventsSelect2Widget::create(
      $this->container,
      [
        'field_definition' => $fieldDefinition,
        'settings' => [],
        'third_party_settings' => [],
      ],
      'linked_events_select2',
      [],
    );
    $build = $widget->formElement($field, 0, $element, $form, new FormState());
    $this->assertEmpty($build['#options']);

    // Test that options are built correctly.
    $field->value = json_encode([
      'id' => 'test',
      'name' => [
        'fi' => 'Testi',
        'en' => 'Test',
      ],
    ]);
    $widget = LinkedEventsSelect2Widget::create(
      $this->container,
      [
        'field_definition' => $fieldDefinition,
        'settings' => [],
        'third_party_settings' => [],
      ],
      'linked_events_select2',
      [],
    );
    $build = $widget->formElement($field, 0, $element, $form, new FormState());
    $this->assertEquals([$field->value => 'Test'], $build['#options']);
  }

}
