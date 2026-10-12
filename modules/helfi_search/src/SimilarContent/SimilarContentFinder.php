<?php

declare(strict_types=1);

namespace Drupal\helfi_search\SimilarContent;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\helfi_platform_config\MultisiteSearch;
use Drupal\helfi_search\DocumentKeyTrait;
use Drupal\helfi_search\EmbeddingModel;
use Drupal\helfi_search\QueryBuilder;
use Drupal\helfi_search\Vector;
use Drupal\search_api\Utility\Utility;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Finds content that is similar to an entity.
 */
final class SimilarContentFinder {

  use DocumentKeyTrait;

  /**
   * Minimum cosine similarity for a chunk to count as similar content.
   */
  private const float SIMILARITY_THRESHOLD = 0.75;

  /**
   * Default maximum number of similar documents to return.
   */
  private const int SIZE = 10;

  /**
   * Number of KNN candidates per shard, per chunk.
   */
  private const int NUM_CANDIDATES = 50;

  public function __construct(
    private readonly Connection $database,
    private readonly MultisiteSearch $multisiteSearch,
    #[Autowire(service: 'helfi_platform_config.etusivu_elastic_client')]
    private readonly Client $elasticClient,
  ) {
  }

  /**
   * Finds documents that have chunks similar to the entity's chunks.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to compare.
   * @param \Drupal\helfi_search\EmbeddingModel $model
   *   The model the chunks were embedded with.
   * @param int $size
   *   Maximum number of documents to return.
   *
   * @return \Drupal\helfi_search\SimilarContent\SimilarDocument[]
   *   The similar documents, most similar first.
   *
   * @throws \Elastic\Elasticsearch\Exception\ElasticsearchException
   * @throws \Elastic\Transport\Exception\TransportException
   */
  public function findSimilar(ContentEntityInterface $entity, EmbeddingModel $model = EmbeddingModel::DEFAULT, int $size = self::SIZE): array {
    $query = $this->database->select(self::CHUNK_TABLE, 'c')
      ->fields('c', ['vector']);

    $rows = self::keyCondition($query, $entity)
      ->condition('model', $model->value)
      ->orderBy('delta')
      ->execute()
      ->fetchCol();

    $vectors = array_values(array_map(Vector::unpack(...), $rows));
    if (!$vectors) {
      return [];
    }

    $language = $entity->language()->getId();
    $ownId = $this->multisiteSearch->addPrefixToId(Utility::createCombinedId(
      'entity:' . $entity->getEntityTypeId(),
      $entity->id() . ':' . $language,
    ));

    $filter = [
      'bool' => [
        'must' => [['term' => ['search_api_language' => $language]]],
        'must_not' => [['ids' => ['values' => [$ownId]]]],
      ],
    ];

    // One KNN clause per chunk. Elasticsearch merges the clauses into a
    // single hit list and sums the scores of a document that several clauses
    // return, so the result is ranked by overall similarity to the entity.
    // https://www.elastic.co/docs/solutions/search/vector/knn#_search_multiple_knn_fields
    $knn = array_map(static fn (array $vector): array => [
      'field' => $model->fieldPrefix() . '.vector',
      'query_vector' => $vector,
      'k' => $size,
      'num_candidates' => max(self::NUM_CANDIDATES, $size),
      // Raw cosine similarity floor.
      'similarity' => self::SIMILARITY_THRESHOLD,
      'filter' => $filter,
    ], $vectors);

    $response = $this->elasticClient->search([
      'index' => QueryBuilder::EMBEDDINGS_INDEX,
      'body' => [
        'knn' => $knn,
        'size' => $size,
        '_source' => ['label', 'url', 'instance', 'markdown'],
      ],
    ]);
    assert($response instanceof Elasticsearch);

    return array_map(
      SimilarDocument::fromHit(...),
      array_values($response->asArray()['hits']['hits'] ?? []),
    );
  }

}
