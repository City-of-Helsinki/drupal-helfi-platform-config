<?php

namespace Drupal\helfi_platform_config\SearchAPI\Query;

use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\elasticsearch_connector\SearchAPI\Query\QueryResultParser as ElasticsearchConnectorQueryResultParser;
use Drupal\helfi_platform_config\MultisiteSearch;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Drupal\elasticsearch_connector\SearchAPI\Query\FacetResultParser;
use Drupal\elasticsearch_connector\SearchAPI\Query\SpellCheckResultParser;

/**
 * Provides a result set parser.
 */
class QueryResultParser extends ElasticsearchConnectorQueryResultParser {

  const MULTISITE_INDEXES = [
    'hyte',
  ];

  /**
   * Creates a new QueryResultParser.
   *
   * @param \Drupal\search_api\Utility\FieldsHelperInterface $fieldsHelper
   *   The fields helper.
   * @param \Drupal\elasticsearch_connector\SearchAPI\Query\FacetResultParser $facetResultParser
   *   The facet result parser.
   * @param \Drupal\elasticsearch_connector\SearchAPI\Query\SpellCheckResultParser $spellCheckResultParser
   *   The spellcheck result parser.
   * @param \Drupal\helfi_platform_config\MultisiteSearch $multisiteSearch
   *   The multisite search helper.
   */
  public function __construct(
    protected FieldsHelperInterface $fieldsHelper,
    protected FacetResultParser $facetResultParser,
    protected SpellCheckResultParser $spellCheckResultParser,
    protected MultisiteSearch $multisiteSearch,
  ) {
  }

  /**
   * Parse a ElasticSearch response into a ResultSetInterface.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   Search API query.
   * @param array $response
   *   Raw response array back from ElasticSearch.
   *
   * @return \Drupal\search_api\Query\ResultSetInterface
   *   The results of the search.
   */
  public function parseResult(QueryInterface $query, array $response): ResultSetInterface {
    $indexId = $query->getIndex()->id();

    if ($this->multisiteSearch->isMultisiteIndex($indexId) && !empty($response['hits']['hits'])) {
      foreach ($response['hits']['hits'] as &$result) {
        // If _id has instance specific prefix for current instance, this result
        // is for current instance. In that case replace the _id with the
        // original id value stored in search_api_id field to match what Search
        // API uses for index tracking.
        if ($this->multisiteSearch->hasCurrentInstancePrefix($result['_id']) && !empty($result['_source']['search_api_id'][0])) {
          $result['_id'] = $result['_source']['search_api_id'][0];
        }
      }
    }

    return parent::parseResult($query, $response);
  }

}
