<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_org_chart;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\InvalidArgumentException;
use GuzzleHttp\Utils;

/**
 * Imports org chart from päätökset instance.
 */
class OrgChartImporter {

  /**
   * Constructs a new instance.
   */
  public function __construct(readonly ClientInterface $client) {}

  /**
   * Gets the uri for given language.
   *
   * @param string $langcode
   *   The langcode.
   * @param string $start
   *   The starting org id.
   * @param int $depth
   *   The chart depth.
   *
   * @return string
   *   The uri.
   */
  private function getUri(string $langcode, string $start, int $depth): string {
    return "https://paatokset.hel.fi/$langcode/ahjo_api/org-chart/$start/$depth";
  }

  /**
   * Fetches the org chart.
   *
   * @param string $langcode
   *   The langcode.
   * @param string $start
   *   The starting org id.
   * @param int $depth
   *   The chart depth.
   *
   * @return array
   *   The data.
   */
  public function fetch(string $langcode, string $start, int $depth) : array {
    try {
      $data = $this->client->request('GET', $this->getUri($langcode, $start, $depth))
        ->getBody()
        ->getContents();
      $chart = Utils::jsonDecode($data, assoc: TRUE);
    }
    catch (GuzzleException | InvalidArgumentException) {
      return [];
    }

    return $chart;
  }

}
