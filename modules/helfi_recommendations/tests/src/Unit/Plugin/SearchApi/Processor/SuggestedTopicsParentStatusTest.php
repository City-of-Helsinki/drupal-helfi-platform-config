<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_recommendations\Unit\Plugin\SearchApi\Processor;

use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\node\Entity\Node;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\IndexInterface;
use Drupal\helfi_recommendations\Plugin\search_api\processor\SuggestedTopicsParentStatus;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\UnitTestCase;
use Drupal\Tests\search_api\Unit\Processor\TestItemsTrait;
use Drupal\helfi_recommendations\Entity\SuggestedTopics;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests the "Suggested topics parent status" processor.
 *
 * @group helfi_recommendations
 */
class SuggestedTopicsParentStatusTest extends UnitTestCase {

  use TestItemsTrait;

  /**
   * The processor to be tested.
   *
   * @var \Drupal\helfi_recommendations\Plugin\search_api\processor\SuggestedTopicsParentStatus
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

    $this->processor = new SuggestedTopicsParentStatus([], 'suggested_topics_parent_status', []);

    $this->index = $this->createMock(IndexInterface::class);

    foreach (['suggested_topics', 'node'] as $entity_type) {
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

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getDefinition')
      ->willReturnCallback(function ($entity_type_id) {
        $entity_type = $this->createMock(EntityTypeInterface::class);
        $publishable = in_array($entity_type_id, ['suggested_topics']);
        $entity_type->method('entityClassImplements')
          ->willReturnMap([
            [EntityPublishedInterface::class, $publishable],
          ]);
        return $entity_type;
      });
    $this->container->set('entity_type.manager', $entity_type_manager);

    $this->assertEquals($expected, SuggestedTopicsParentStatus::supportsIndex($this->index));
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
      'suggested topics datasource' => [['entity:suggested_topics'], TRUE],
      'node datasource' => [['entity:node'], FALSE],
    ];
  }

  /**
   * Tests removing items with unpublished or missing parent entities.
   */
  public function testAlterItems() {
    $fields_helper = \Drupal::getContainer()->get('search_api.fields_helper');
    $items = [];
    $datasource_id = "entity:suggested_topics";

    // Add the following test items:
    // 1. published parent.
    // 2. unpublished parent.
    // 3. no parent.
    foreach ([1 => TRUE, 2 => FALSE, 3 => NULL] as $i => $status) {
      $item_id = Utility::createCombinedId($datasource_id, "$i:und");
      $item = $fields_helper->createItem($this->index, $item_id, $this->datasources[$datasource_id]);
      $entity = $this->getMockBuilder(SuggestedTopics::class)
        ->disableOriginalConstructor()
        ->getMock();
      $parent = $this->getMockBuilder(Node::class)
        ->disableOriginalConstructor()
        ->getMock();
      if ($status !== NULL) {
        $parent->method('isPublished')
          ->willReturn($status);
        $entity->method('getParentEntity')
          ->willReturn($parent);
      }
      else {
        $entity->method('getParentEntity')
          ->willReturn(NULL);
      }
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $item->setOriginalObject(EntityAdapter::createFromEntity($entity));
      $items[$item_id] = $item;
    }

    $this->processor->alterIndexedItems($items);
    $expected = [
      Utility::createCombinedId('entity:suggested_topics', '1:und'),
    ];
    $this->assertEquals($expected, array_keys($items));
  }

}
