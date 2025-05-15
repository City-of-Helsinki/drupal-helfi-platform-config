<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_org_chart;

use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_api_base\Features\FeatureManagerInterface;
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
  public function __construct(
    private readonly ClientInterface $client,
    private readonly FeatureManagerInterface $featureManager,
    private readonly EnvironmentResolverInterface $environmentResolver,
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
    try {
      $environment = $this
        ->environmentResolver
        ->getEnvironment(Project::PAATOKSET, $this->environmentResolver->getActiveEnvironmentName());
    }
    catch (\InvalidArgumentException) {
      $environment = $this
        ->environmentResolver
        ->getEnvironment(Project::PAATOKSET, EnvironmentEnum::Prod->value);
    }

    // We want to use public URL so that paatokset generates publicly accessible URLs
    // for organizations. However, public URL does not work in local environment.
    if ($environment->getEnvironment() === EnvironmentEnum::Local) {
      return sprintf("%s/ahjo_api/org-chart/$start/$depth", $environment->getInternalAddress($langcode));
    }

    return sprintf("%s/ahjo_api/org-chart/$start/$depth", $environment->getUrl($langcode));
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
    if ($this->featureManager->isEnabled(FeatureManagerInterface::USE_MOCK_RESPONSES)) {
      $data = file_get_contents(__DIR__ . "/../tests/fixtures/org-chart-$depth.json");
      return Utils::jsonDecode($data, assoc: TRUE);
    }

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
