<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_react_search\Kernel\Plugin;

use Drupal\Core\Form\FormState;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\helfi_react_search\Plugin\Field\FieldWidget\LinkedEventsSelect2Widget;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * Tests linked events widget.
 *
 * @group helfi_react_search
 */
class LinkedEventsSelect2WidgetTest extends KernelTestBase {

  use ApiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'diff',
    'helfi_api_base',
    'helfi_react_search',
    'entity_test',
    'select2',
    'config_rewrite',
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

  /**
   * Tests widget that loads options from a keyword set.
   */
  public function testKeywordSetWidget(): void {
    $this->container->set('http_client', $this->createMockHttpClient([
      // Simulate API failure.
      new RequestException('test failure', new Request('GET', 'test'), new Response(504)),
      // Response from linked events api:
      // https://api.hel.fi/linkedevents/v1/keyword_set/helsinki:audiences/?include=keywords.
      new Response(body: json_encode([
        'id' => 'helsinki:audiences',
        'keywords' => [
          ['id' => 'yso:p11617', 'name' => ['fi' => 'nuoret', 'en' => 'young people']],
          ['id' => 'yso:p2433', 'name' => ['fi' => 'ikääntyneet', 'en' => 'older people']],
        ],
      ], JSON_THROW_ON_ERROR)),
    ]));

    FieldStorageConfig::load('entity_test.field_test')
      ->setCardinality(-1)
      ->save();
    $fieldDefinition = FieldConfig::create([
      'field_name' => 'field_test',
      'label' => 'A test field',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ]);
    $fieldDefinition->save();

    $build = function (EntityTest $entity) use ($fieldDefinition): array {
      $form = [];
      $widget = LinkedEventsSelect2Widget::create(
        $this->container,
        [
          'field_definition' => $fieldDefinition,
          'settings' => ['keyword_set' => 'helsinki:audiences'],
          'third_party_settings' => [],
        ],
        'linked_events_select2',
        [],
      );
      return $widget->formElement($entity->get('field_test'), 0, ['#required' => FALSE], $form, new FormState());
    };

    // API failure results in no options.
    $element = $build(EntityTest::create());
    $this->assertEmpty($element['#options']);
    $this->assertArrayNotHasKey('#autocomplete', $element);

    $expected = [
      '{"id":"yso:p2433","name":{"fi":"ik\\u00e4\\u00e4ntyneet","en":"older people"}}' => 'older people',
      '{"id":"yso:p11617","name":{"fi":"nuoret","en":"young people"}}' => 'young people',
    ];
    $element = $build(EntityTest::create());
    $this->assertSame($expected, $element['#options']);
    $this->assertTrue($element['#multiple']);

    // Options are cached, so no further requests are made. Selected
    // values are kept as they are even if the API data has changed.
    $selected = '{"id":"yso:p11617","name":{"en":"youth"}}';
    $element = $build(EntityTest::create(['field_test' => [$selected]]));
    $this->assertSame([
      array_key_first($expected) => 'older people',
      $selected => 'youth',
    ], $element['#options']);
  }

}
