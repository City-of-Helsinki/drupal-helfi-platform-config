<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_news_list\Kernel\ExternalEntityStorage;

use Drupal\external_entities\Entity\Query\External\Query;
use Elastic\Elasticsearch\Client;

/**
 * Tests news tags storage client.
 *
 * @group helfi_paragraphs_news_list
 */
class NewsNeighbourhoodsStorageClientTest extends TermStorageClientTestBase {

  /**
   * {@inheritdoc}
   */
  protected function getStorageName(): string {
    return 'helfi_news_neighbourhoods';
  }

  /**
   * {@inheritdoc}
   */
  protected function getVid(): string {
    return 'news_neighbourhoods';
  }

  /**
   * Tests geo_distance sorting.
   */
  public function testGeoDistanceQuery(): void {
    $client = $this->prophesize(Client::class);
    // Test geo distance sort.
    $client->search([
      'index' => 'news_terms',
      'body' => [
        'sort' => [
          [
            '_geo_distance' => [
              'field_location' => [
                'lat' => 48.8584,
                'lon' => 2.2945,
              ],
              'unit' => 'km',
              'order' => 'asc',
              'distance_type' => 'plane',
              'mode' => 'min',
              'ignore_unmapped' => FALSE,
            ],
          ],
        ],
        'query' => [
          'bool' => [
            'must' => [
              ['term' => ['vid' => $this->getVid()]],
            ],
          ],
        ],
      ],
    ])
      ->shouldBeCalled()
      ->willReturn($this->createElasticsearchResponse([]));

    $query = $this->getSut($client->reveal())
      ->getQuery();

    $this->assertInstanceOf(Query::class, $query);

    // Drupal query interface is not quite flexible enough to support all the
    // options and parameters geo_distance sort needs, so the implementation
    // uses setParameter from external_entities Query class.

    $query_parameter = [
      'location', [
        [
          'lat' => 48.8584,
          'lon' => 2.2945,
        ],
        [
          'unit' => 'km',
          // Geo distance sort direction.
          'order' => 'asc',
          // 'arc' is more accurate, but within
          // a city it should not matter.
          'distance_type' => 'plane',
          // What to do in case a field has several geo points.
          'mode' => 'min',
          // Unmapped field cause the search to fail.
          'ignore_unmapped' => FALSE,
        ],
      ], 'GEO_DISTANCE_SORT'
    ];

    $query->setParameters([$query_parameter]);

    $query->accessCheck(FALSE)->execute();
  }

}
