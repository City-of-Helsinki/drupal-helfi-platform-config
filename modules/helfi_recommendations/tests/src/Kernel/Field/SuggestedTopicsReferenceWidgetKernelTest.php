<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_recommendations\Kernel\Field;

use DG\BypassFinals;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\helfi_recommendations\Plugin\Field\FieldType\SuggestedTopicsReferenceItem;
use Drupal\helfi_recommendations\Plugin\Field\FieldWidget\SuggestedTopicsReferenceWidget;
use Drupal\helfi_recommendations\RecommendationManagerInterface;
use Drupal\Tests\helfi_recommendations\Kernel\AnnifKernelTestBase;
use Prophecy\Argument;

/**
 * Tests suggested topics reference widget.
 *
 * @group helfi_platform_config
 */
class SuggestedTopicsReferenceWidgetKernelTest extends AnnifKernelTestBase {

  const INSTANCES = [
    'test1' => 'Test 1',
    'test2' => 'Test 2',
  ];
  const CONTENT_TYPES = [
    'type1|bundle1' => 'Test 1',
    'type1|bundle2' => 'Test 2',
    'type2|bundle1' => 'Test 3',
    'type2|bundle2' => 'Test 4',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
  ];

  /**
   * Mocked recommendation manager.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $recommendationManager;

  /**
   * Field items.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $fieldItems;

  /**
   * Field item.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $fieldItem;

  /**
   * Mocked form state.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $formState;

  /**
   * Mocked parent entity.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $parentEntity;

  /**
   * Field widget to test.
   *
   * @var \Drupal\helfi_recommendations\Plugin\Field\FieldWidget\SuggestedTopicsReferenceWidget
   */
  protected $fieldWidget;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    BypassFinals::enable();
    parent::setUp();

    $this->recommendationManager = $this->prophesize(RecommendationManagerInterface::class);
    $this->recommendationManager->getAllowedInstances()->willReturn(self::INSTANCES);
    $this->recommendationManager->getAllowedContentTypesAndBundles()->willReturn(self::CONTENT_TYPES);
    $this->container->set(RecommendationManagerInterface::class, $this->recommendationManager->reveal());
    $this->fieldItem = $this->prophesize(SuggestedTopicsReferenceItem::class);
    $this->fieldItem->isEmpty()->willReturn(TRUE);
    $this->parentEntity = $this->prophesize(EntityInterface::class);
    $this->parentEntity->id()->willReturn('123');
    $this->parentEntity->getEntityTypeId()->willReturn('node');
    $this->parentEntity->bundle()->willReturn('test_bundle');
    $this->fieldItems = $this->prophesize(FieldItemListInterface::class);
    $this->fieldItems->offsetExists(0)->willReturn(TRUE);
    $this->fieldItems->offsetGet(0)->willReturn($this->fieldItem->reveal());
    $this->fieldItems->getEntity()->willReturn($this->parentEntity->reveal());
    $this->formState = $this->prophesize(FormStateInterface::class);

    $booleanField = $this->prophesize(TypedDataInterface::class);
    $booleanField->getValue()->willReturn(TRUE, TRUE);
    $this->fieldItem->get('published')->willReturn($booleanField->reveal());
    $this->fieldItem->get('show_block')->willReturn($booleanField->reveal());

    $arrayField = $this->prophesize(TypedDataInterface::class);
    $arrayField->getValue()->willReturn([], []);
    $this->fieldItem->get('instances')->willReturn($arrayField->reveal());
    $this->fieldItem->get('content_types')->willReturn($arrayField->reveal());

    $fieldDefinition = $this->prophesize(FieldDefinitionInterface::class);
    $fieldStorageDefinition = $this->prophesize(FieldStorageDefinitionInterface::class);
    $fieldPropertyDefinition = $this->prophesize(DataDefinitionInterface::class);
    $this->fieldItem->getFieldDefinition()->willReturn($fieldDefinition->reveal());
    $fieldDefinition->getFieldStorageDefinition()->willReturn($fieldStorageDefinition->reveal());
    $fieldStorageDefinition->getPropertyDefinition(Argument::any())->willReturn($fieldPropertyDefinition->reveal());
    $fieldPropertyDefinition->getLabel()->willReturn('Test label');

    $configuration = [
      'field_definition' => $this->prophesize(FieldDefinitionInterface::class)->reveal(),
      'settings' => [],
      'third_party_settings' => [],
    ];

    $this->fieldWidget = SuggestedTopicsReferenceWidget::create($this->container, $configuration, 'suggested_topics_reference', []);
  }

  /**
   * Tests the formElement method.
   */
  public function testFormElement(): void {
    $form = [];
    $element = $this->fieldWidget->formElement($this->fieldItems->reveal(), 0, [], $form, $this->formState->reveal());

    $this->assertEquals(TRUE, $element['published']['#default_value']);
    $this->assertEquals(TRUE, $element['show_block']['#default_value']);
    $this->assertEquals([], $element['instances']['#default_value']);
    $this->assertEquals([], $element['content_types']['#default_value']);
    $this->assertEquals(self::INSTANCES, $element['instances']['#options']);
    $this->assertEquals(self::CONTENT_TYPES, $element['content_types']['#options']);
    $this->assertEquals(TRUE, $element['instances']['#access']);
    $this->assertEquals(TRUE, $element['content_types']['#access']);
  }

  /**
   * Tests the formElement method with news item bundle.
   */
  public function testFormElementWithNewsItemBundle(): void {
    $this->parentEntity->bundle()->willReturn('news_item');
    $form = [];
    $element = $this->fieldWidget->formElement($this->fieldItems->reveal(), 0, [], $form, $this->formState->reveal());

    $this->assertEquals(TRUE, $element['published']['#default_value']);
    $this->assertEquals(TRUE, $element['show_block']['#default_value']);
    $this->assertEquals([], $element['instances']['#default_value']);
    $this->assertEquals([], $element['content_types']['#default_value']);
    $this->assertEquals(self::INSTANCES, $element['instances']['#options']);
    $this->assertEquals(self::CONTENT_TYPES, $element['content_types']['#options']);
    $this->assertEquals(FALSE, $element['instances']['#access']);
    $this->assertEquals(FALSE, $element['content_types']['#access']);
  }

}
