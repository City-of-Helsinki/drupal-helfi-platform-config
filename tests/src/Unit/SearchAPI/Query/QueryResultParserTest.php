<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\SearchAPI\Query;

use Drupal\elasticsearch_connector\SearchAPI\Query\FacetResultParser;
use Drupal\elasticsearch_connector\SearchAPI\Query\SpellCheckResultParser;
use Drupal\helfi_platform_config\MultisiteSearch;
use Drupal\helfi_platform_config\SearchAPI\Query\QueryResultParser;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSet;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;

/**
 * Tests the QueryResultParser class.
 */
class QueryResultParserTest extends UnitTestCase {

  /**
   * Tests the parseResult method.
   */
  public function testParseResult() {
    $item = $this->prophesize(ItemInterface::class);
    $field = $this->prophesize(FieldInterface::class);
    $fieldsHelper = $this->prophesize(FieldsHelperInterface::class);
    $fieldsHelper->createItem(Argument::any(), Argument::any())->willReturn($item->reveal());
    $fieldsHelper->createField(Argument::any(), Argument::any(), Argument::any())->willReturn($field->reveal());

    $facetResultParser = $this->prophesize(FacetResultParser::class);
    $spellCheckResultParser = $this->prophesize(SpellCheckResultParser::class);
    $multisiteSearch = $this->prophesize(MultisiteSearch::class);

    $index = $this->prophesize(IndexInterface::class);
    $index->id()->willReturn('index_1');
    $query = $this->prophesize(QueryInterface::class);
    $resultSet = new ResultSet($query->reveal());
    $query->getIndex()->willReturn($index->reveal());
    $query->getResults()->willReturn($resultSet);

    $queryResultParser = new QueryResultParser(
      $fieldsHelper->reveal(),
      $facetResultParser->reveal(),
      $spellCheckResultParser->reveal(),
      $multisiteSearch->reveal(),
    );

    $response = [
      'hits' => [
        'total' => [
          'value' => 3,
        ],
        'hits' => [
          [
            '_id' => 'has_prefix_item_1',
            '_score' => 1.0,
            '_source' => [
              'search_api_id' => ['item_1'],
            ],
          ],
          [
            '_id' => 'item_2',
            '_score' => 1.0,
            '_source' => [
              'search_api_id' => 'item_2',
            ],
          ],
          [
            '_id' => 'has_prefix_item_3',
            '_score' => 1.0,
            '_source' => [],
          ],
        ],
      ],
    ];
    $multisiteSearch->hasCurrentInstancePrefix('has_prefix_item_1')->willReturn(TRUE);
    $multisiteSearch->hasCurrentInstancePrefix('has_prefix_item_3')->willReturn(TRUE);
    $multisiteSearch->hasCurrentInstancePrefix('item_2')->willReturn(FALSE);

    $multisiteSearch->isMultisiteIndex('index_1')->willReturn(TRUE);
    $resultSet = $queryResultParser->parseResult($query->reveal(), $response);
    $result = $resultSet->getExtraData('elasticsearch_response');
    $this->assertEquals('item_1', $result['hits']['hits'][0]['_id']);
    $this->assertEquals('item_2', $result['hits']['hits'][1]['_id']);
    $this->assertEquals('has_prefix_item_3', $result['hits']['hits'][2]['_id']);

    $multisiteSearch->isMultisiteIndex('index_1')->willReturn(FALSE);
    $resultSet = $queryResultParser->parseResult($query->reveal(), $response);
    $result = $resultSet->getExtraData('elasticsearch_response');
    $this->assertEquals('has_prefix_item_1', $result['hits']['hits'][0]['_id']);
    $this->assertEquals('item_2', $result['hits']['hits'][1]['_id']);
    $this->assertEquals('has_prefix_item_3', $result['hits']['hits'][2]['_id']);
  }

}
