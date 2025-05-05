<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_org_chart;

use Drupal\Core\Cache\CacheBackendInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Org chart storage.
 */
class OrgChartStorage {

  /**
   * Constructs a new instance.
   */
  public function __construct(
    #[Autowire('@cache.default')]
    private readonly CacheBackendInterface $cache,
    private readonly OrgChartImporter $importer,
  ) {
  }

  /**
   * Gets the storage key for given language.
   *
   * @param string $langcode
   *   The langcode.
   * @param string $start
   *   The starting org id.
   * @param int $depth
   *   The chart depth.
   *
   * @return string
   *   The storage key.
   */
  private function getStorageKey(string $langcode, string $start, int $depth) : string {
    return sprintf('org_chart_data_%s_%s_%d', $langcode, strtolower(trim($start)), $depth);
  }

  /**
   * Loads the data for given language.
   *
   * @param string $langcode
   *   The language.
   * @param string $start
   *   The starting org id.
   * @param int $depth
   *   The chart depth.
   *
   * @return array
   *   The data.
   */
  public function load(string $langcode, string $start, int $depth) : array {
    $cid = $this->getStorageKey($langcode, $start, $depth);

    if ($data = $this->cache->get($cid)) {
      return json_decode($data->data, associative: TRUE);
    }

    $data = $this->importer->fetch($langcode, $start, $depth);

    if (!empty($data)) {
      // Cache the http request for 12 hours.
      $expire = (new \DateTimeImmutable())
        ->add(\DateInterval::createFromDateString('12 hours'))
        ->getTimestamp();

      $this->cache->set($cid, json_encode($data), $expire);

    }

    return $data;
  }

}
