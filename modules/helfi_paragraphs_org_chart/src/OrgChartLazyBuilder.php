<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_org_chart;

use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Lazy builder for org chart.
 */
class OrgChartLazyBuilder implements TrustedCallbackInterface {

  /**
   * Constructs a new instance.
   */
  public function __construct(private readonly OrgChartImporter $storage) {}

  /**
   * A lazy loader callback to build org chart.
   *
   * @param string $langcode
   *   The langcode.
   * @param string $start
   *   The starting org id.
   * @param int $depth
   *   The chart depth.
   *
   * @return array
   *   The render array.
   */
  public function build(string $langcode, string $start, int $depth) : array {
    $data = $this->storage->fetch($langcode, $start, $depth);
    return [
      '#theme' => 'org_chart',
      '#chart' => $data,
      '#cache' => [
        // Cache for 1 day on successful requests.
        'max-age' => empty($data) ? 60 : 86400,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['build'];
  }

}
