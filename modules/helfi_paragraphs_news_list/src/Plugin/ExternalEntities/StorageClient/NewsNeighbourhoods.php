<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_news_list\Plugin\ExternalEntities\StorageClient;

/**
 * External entity storage client for News neighbourhoods taxonomy terms.
 *
 * @ExternalEntityStorageClient(
 *   id = "helfi_news_neighbourhoods",
 *   label = @Translation("Helfi: News neighbourhoods"),
 *   description = @Translation("Retrieves news neighbourhoods taxonomy terms from Helfi")
 * )
 */
final class NewsNeighbourhoods extends TermBase {

  /**
   * {@inheritdoc}
   */
  protected string $vid = 'news_neighbourhoods';

  /**
   * {@inheritdoc}
   */
  protected function getFieldMapping(string $field) : string {
    return match($field) {
      'location' => 'field_location',
      default => parent::getFieldMapping($field),
    };
  }

  /**
   * Load nearest News neighbourhoods terms.
   *
   * Drupal query interface is not quite flexible enough to implement
   * geo_distance sorting.
   *
   * @param float $lat
   *   Latitude.
   * @param float $lon
   *   Longitude.
   * @param int|null $start
   *   (optional) The first item to return.
   * @param int|null $length
   *   (optional) The number of items to return.
   *
   * @return array
   */
  public function loadByCoordinates(
    float $lat,
    float $lon,
    ?int $start = NULL,
    ?int $length = NULL,
  ): array {
    return $this->query([], [
      [
        'field' => '_geo_distance',
        'direction' => 'ASC',
        'options' => [
          $this->getFieldMapping('location') => [
            'lat' => $lat,
            'lon' => $lon,
          ],
          'unit' => 'km',
          // Arc is more accurate, but within
          // the city it should not matter.
          'distance_type' => 'plane',
          // What to do in case a field has several geo points.
          'mode' => 'min',
          // Unmapped field cause the search to fail.
          'ignore_unmapped' => false
        ],
      ],
    ], $start, $length);
  }

}
