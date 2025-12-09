<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_react_search\Unit\Plugin\SearchApi\Processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\IndexInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\Tests\search_api\Unit\Processor\TestItemsTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Base class for service processor tests.
 *
 * @group helfi_react_search
 */
abstract class ServiceTestBase extends UnitTestCase {

  use TestItemsTrait;

  /**
   * The processor to be tested.
   *
   * @var \Drupal\search_api\Processor\ProcessorPluginBase
   */
  protected $processor;

  /**
   * The test index.
   *
   * @var \Drupal\search_api\IndexInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $index;

  /**
   * The test index's potential datasources.
   *
   * @var \Drupal\search_api\Datasource\DatasourceInterface[]
   */
  protected $datasources = [];

  /**
   * Creates a new processor object for use in the tests.
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setUpMockContainer();
    $this->container->set('string_translation', $this->getStringTranslationStub());

    $this->index = $this->createMock(IndexInterface::class);

    foreach (['node', 'tpr_service', 'tpr_unit'] as $entity_type) {
      $datasource = $this->createMock(DatasourceInterface::class);
      $datasource->expects($this->any())
        ->method('getEntityTypeId')
        ->willReturn($entity_type);
      $this->datasources["entity:$entity_type"] = $datasource;
    }
  }

  /**
   * Tests supportsIndex().
   *
   * @param string[]|null $datasource_ids
   *   The IDs of datasources the index should have, or NULL if it should have
   *   all of them.
   * @param bool $expected
   *   Whether the processor is supposed to support that index.
   */
  #[DataProvider('supportsIndexDataProvider')]
  public function testSupportsIndex(?array $datasource_ids, bool $expected): void {
    if ($datasource_ids !== NULL) {
      $datasource_ids = array_flip($datasource_ids);
      $this->datasources = array_intersect_key($this->datasources, $datasource_ids);
    }
    $this->index->method('getDatasources')
      ->willReturn($this->datasources);

    $this->assertEquals($expected, $this->processor->supportsIndex($this->index));
  }

  /**
   * Provides data for the testSupportsIndex() tests.
   *
   * @return array[]
   *   Array of parameter arrays for testSupportsIndex().
   */
  public static function supportsIndexDataProvider(): array {
    return [
      'all datasources' => [NULL, TRUE],
      'tpr service datasource' => [['entity:tpr_service'], TRUE],
      'node datasource' => [['entity:node'], FALSE],
    ];
  }

}
