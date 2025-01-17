<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_news_list\Kernel\ExternalEntityStorage;

use Drupal\helfi_paragraphs_news_list\Plugin\ExternalEntities\StorageClient\NewsNeighbourhoods;
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
  public function testLocationQuery(): void {
    $client = $this->prophesize(Client::class);
    // Test geo distance sort.
    $client->search([
      'index' => 'news_terms',
      'body' => [
        'sort' => [
          '_geo_distance' => [
            'order' => 'asc',
            'field_location' => [
              'lat' => 48.8584,
              'lon' => 2.2945,
            ],
            'unit' => 'km',
            'distance_type' => 'plane',
            'mode' => 'min',
            'ignore_unmapped' => FALSE,
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

    $sut = $this->getSut($client->reveal())->getStorageClient();
    $this->assertInstanceOf(NewsNeighbourhoods::class, $sut);

    $sut->loadByCoordinates(48.8584, 2.2945);
  }

}
