<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_recommendations\Kernel\SearchApi\Processor;

use Drupal\elasticsearch_connector\SearchAPI\BackendClientFactory;
use Drupal\elasticsearch_connector\SearchAPI\BackendClientInterface;
use Drupal\helfi_recommendations\Entity\SuggestedTopics;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Processor\ProcessorPropertyInterface;
use Drupal\taxonomy\Entity\Term;
use Prophecy\Argument;

/**
 * Tests the scored reference processor.
 *
 * @group helfi_recommendations
 */
class ScoredReferenceProcessorTest extends ProcessorTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp($processor = NULL): void {
    parent::setUp();

    $searchApiField = new Field($this->index, 'keywords');
    $searchApiField->setType('scored_item');
    $searchApiField->setPropertyPath('keywords_scored');
    $searchApiField->setLabel('Test field');
    $searchApiField->setDatasourceId('entity:suggested_topics');

    $this->index->addField($searchApiField);
    $this->index->setOption('index_directly', TRUE);
    $this->index->save();
  }

  /**
   * Tests that field values are added correctly.
   */
  public function testDatasource() : void {
    /** @var \Drupal\search_api\Utility\PluginHelperInterface $pluginHelper */
    $pluginHelper = $this->container->get('search_api.plugin_helper');

    $term = Term::create([
      'name' => $this->randomMachineName(),
      'vid' => $this->vocabulary->id(),
    ]);
    $term->save();

    SuggestedTopics::create([
      'keywords' => [
        [
          'entity' => $term,
          'score' => 0.5,
        ],
      ],
    ])->save();

    $datasource = $pluginHelper->createDatasourcePlugin($this->index, 'entity:suggested_topics');
    $sut = $pluginHelper->createProcessorPlugin($this->index, 'scored_reference');

    $properties = $sut->getPropertyDefinitions(NULL);
    $this->assertEmpty($properties);

    $properties = $sut->getPropertyDefinitions($datasource);
    $this->assertNotEmpty($properties);
    $this->assertArrayHasKey('keywords_scored', $properties);
    $this->assertInstanceOf(ProcessorPropertyInterface::class, $properties['keywords_scored']);
  }

  /**
   * Tests that field values are added correctly.
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

    $term = Term::create([
      'name' => $this->randomMachineName(),
      'vid' => $this->vocabulary->id(),
    ]);
    $term->save();

    SuggestedTopics::create([
      'keywords' => [
        [
          'entity' => $term,
          'score' => 0.5,
        ],
      ],
    ])->save();

    $this->triggerPostRequestIndexing();
  }

}
