<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_recommendations\Kernel\SearchApi\Processor;

use Drupal\elasticsearch_connector\SearchAPI\BackendClientFactory;
use Drupal\elasticsearch_connector\SearchAPI\BackendClientInterface;
use Drupal\helfi_recommendations\Entity\SuggestedTopics;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Processor\ProcessorPropertyInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\search_api\Utility\Utility;
use Prophecy\Argument;

/**
 * Tests the scored reference processor.
 *
 * @group helfi_recommendations
 * @coversDefaultClass \Drupal\helfi_recommendations\Plugin\search_api\processor\ScoredReferenceParentProcessor
 */
class ScoredReferenceParentProcessorTest extends ProcessorTestBase {

  /**
   * Additional modules to enable.
   *
   * @var string[]
   */
  protected static $modules = [
    'node',
  ];

  /**
   * Test nodes.
   *
   * @var \Drupal\node\Entity\Node[]
   */
  protected $nodes;

  /**
   * Test suggested topics entities.
   *
   * @var \Drupal\helfi_recommendations\Entity\SuggestedTopics[]
   */
  protected $suggestions;

  /**
   * {@inheritdoc}
   */
  public function setUp($processor = NULL): void {
    parent::setUp();

    $string_fields = [
      'parent_url_fi',
      'parent_url_sv',
      'parent_url_en',
      'parent_title_fi',
      'parent_title_sv',
      'parent_title_en',
      'parent_image_url',
      'parent_image_alt_fi',
      'parent_image_alt_sv',
      'parent_image_alt_en',
    ];
    $date_fields = [
      'parent_published_at',
    ];

    foreach ($string_fields as $field) {
      $searchApiField = new Field($this->index, $field);
      $searchApiField->setType('string');
      $searchApiField->setPropertyPath($field);
      $searchApiField->setLabel($field);
      $searchApiField->setDatasourceId('entity:suggested_topics');
      $this->index->addField($searchApiField);
    }

    foreach ($date_fields as $field) {
      $searchApiField = new Field($this->index, $field);
      $searchApiField->setType('date');
      $searchApiField->setPropertyPath($field);
      $searchApiField->setLabel($field);
      $searchApiField->setDatasourceId('entity:suggested_topics');
      $this->index->addField($searchApiField);
    }

    $this->index->setOption('index_directly', TRUE);
    $this->index->save();

    NodeType::create([
      'name' => $this->randomMachineName(),
      'type' => 'test_node_bundle',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'test_keywords',
      'entity_type' => 'node',
      'type' => 'suggested_topics_reference',
    ])->save();

    FieldConfig::create([
      'field_name' => 'test_keywords',
      'entity_type' => 'node',
      'bundle' => 'test_node_bundle',
      'label' => 'Test field',
    ])->save();

    $term = Term::create([
      'name' => $this->randomMachineName(),
      'vid' => $this->vocabulary->id(),
    ]);
    $term->save();

    $this->suggestions[0] = SuggestedTopics::create([
      'keywords' => [
        ['entity' => $term, 'score' => 0.8],
      ],
    ]);
    $this->suggestions[0]->save();

    $this->nodes[0] = Node::create([
      'type' => 'test_node_bundle',
      'title' => $this->randomString(),
      'test_keywords' => $this->suggestions[0],
    ]);
    $this->nodes[0]->save();
  }

  /**
   * Tests that field values are added correctly.
   *
   * @covers ::getPropertyDefinitions
   */
  public function testDatasource() : void {
    /** @var \Drupal\search_api\Utility\PluginHelperInterface $pluginHelper */
    $pluginHelper = $this->container->get('search_api.plugin_helper');

    $datasource = $pluginHelper->createDatasourcePlugin($this->index, 'entity:suggested_topics');
    $sut = $pluginHelper->createProcessorPlugin($this->index, 'scored_reference_parent');

    $properties = $sut->getPropertyDefinitions(NULL);
    $this->assertEmpty($properties);

    $properties = $sut->getPropertyDefinitions($datasource);
    $this->assertNotEmpty($properties);
    $this->assertArrayHasKey('parent_url_fi', $properties);
    $this->assertInstanceOf(ProcessorPropertyInterface::class, $properties['parent_url_fi']);
    $this->assertArrayHasKey('parent_url_sv', $properties);
    $this->assertInstanceOf(ProcessorPropertyInterface::class, $properties['parent_url_sv']);
    $this->assertArrayHasKey('parent_url_en', $properties);
    $this->assertInstanceOf(ProcessorPropertyInterface::class, $properties['parent_url_en']);
    $this->assertArrayHasKey('parent_title_fi', $properties);
    $this->assertInstanceOf(ProcessorPropertyInterface::class, $properties['parent_title_fi']);
    $this->assertArrayHasKey('parent_title_sv', $properties);
    $this->assertInstanceOf(ProcessorPropertyInterface::class, $properties['parent_title_sv']);
    $this->assertArrayHasKey('parent_title_en', $properties);
    $this->assertInstanceOf(ProcessorPropertyInterface::class, $properties['parent_title_en']);
    $this->assertArrayHasKey('parent_image_url', $properties);
    $this->assertInstanceOf(ProcessorPropertyInterface::class, $properties['parent_image_url']);
    $this->assertArrayHasKey('parent_image_alt_fi', $properties);
    $this->assertInstanceOf(ProcessorPropertyInterface::class, $properties['parent_image_alt_fi']);
    $this->assertArrayHasKey('parent_image_alt_sv', $properties);
    $this->assertInstanceOf(ProcessorPropertyInterface::class, $properties['parent_image_alt_sv']);
    $this->assertArrayHasKey('parent_image_alt_en', $properties);
    $this->assertInstanceOf(ProcessorPropertyInterface::class, $properties['parent_image_alt_en']);
    $this->assertArrayHasKey('parent_published_at', $properties);
    $this->assertInstanceOf(ProcessorPropertyInterface::class, $properties['parent_published_at']);
  }

  /**
   * Tests that field values are added correctly.
   *
   * @covers ::addFieldValues
   */
  public function testAddFieldValues() : void {
    $backend = $this->prophesize(BackendClientInterface::class);
    $backend
      ->indexItems(Argument::any(), Argument::any())
      ->shouldBeCalled()
      ->willReturn([]);

    $backend
      ->addIndex(Argument::any());

    $backendFactory = $this->prophesize(BackendClientFactory::class);
    $backendFactory->create(Argument::any(), Argument::any())
      ->willReturn($backend->reveal());

    $this->container->set('elasticsearch_connector.backend_client_factory', $backendFactory->reveal());

    $this->triggerPostRequestIndexing();

    // Extract field values and check the values.
    $id = Utility::createCombinedId('entity:suggested_topics', $this->suggestions[0]->id() . ':en');
    $item = \Drupal::getContainer()
      ->get('search_api.fields_helper')
      ->createItemFromObject($this->index, $this->suggestions[0]->getTypedData(), $id);

    $fields = $item->getFields();
    $this->assertEquals([$this->nodes[0]->toUrl('canonical', ['absolute' => TRUE])->toString()], $fields['parent_url_en']->getValues());
    $this->assertEmpty($fields['parent_url_fi']->getValues());
    $this->assertEmpty($fields['parent_url_sv']->getValues());
    $this->assertEmpty($fields['parent_title_fi']->getValues());
    $this->assertEmpty($fields['parent_title_sv']->getValues());
    $this->assertEquals([$this->nodes[0]->label()], $fields['parent_title_en']->getValues());
    $this->assertEmpty($fields['parent_image_alt_fi']->getValues());
    $this->assertEmpty($fields['parent_image_alt_sv']->getValues());
    $this->assertEmpty($fields['parent_image_alt_en']->getValues());
    $this->assertEmpty($fields['parent_published_at']->getValues());
  }

}
