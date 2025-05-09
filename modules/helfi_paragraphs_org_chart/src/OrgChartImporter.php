<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_org_chart;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\helfi_api_base\Features\FeatureManager;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\InvalidArgumentException;
use GuzzleHttp\Utils;

/**
 * Imports org chart from päätökset instance.
 */
class OrgChartImporter {

  use StringTranslationTrait;

  /**
   * Constructs a new instance.
   */
  public function __construct(
    private readonly ClientInterface $client,
    private readonly FeatureManager $featureManager,
  ) {
  }

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
    // Return mock response if the use mock feature is enabled.
    if ($this->featureManager->isEnabled(FeatureManager::USE_MOCK_RESPONSES)) {
      $data = file_get_contents(__DIR__ . '/../tests/fixtures/org-chart.json');
      return Utils::jsonDecode($data, assoc: TRUE);
    }

    try {
      $data = $this->client->request('GET', $this->getUri($langcode, $start, $depth))
        ->getBody()
        ->getContents();
      $chart = Utils::jsonDecode($data, assoc: TRUE);
    }
    catch (GuzzleException | InvalidArgumentException) {
      return [
        'error' => TRUE,
        'error_title' => $this->t('An error occurred while loading the content', [], ['context' => 'Organization chart error']),
        'error_body' => $this->t('Please reload the page or try again later.', [], ['context' => 'Organization chart error']),
      ];
    }

    return $chart;
  }

}
