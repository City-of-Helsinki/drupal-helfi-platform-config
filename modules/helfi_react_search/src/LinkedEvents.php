<?php

declare(strict_types=1);

namespace Drupal\helfi_react_search;

use Drupal\helfi_api_base\Features\FeatureManagerInterface;

/**
 * Class for retrieving data from LinkedEvents Api.
 */
class LinkedEvents {

  public const BASE_URL = 'https://tapahtumat.hel.fi';
  public const FIXTURE_NAME = 'fixture-linked-events';

  /**
   * Class constructor.
   *
   * @param \Drupal\helfi_api_base\Features\FeatureManagerInterface $featureManager
   *   The feature manager.
   */
  public function __construct(
    private readonly FeatureManagerInterface $featureManager,
  ) {
  }

  /**
   * Get fixture for javascript.
   *
   * @return mixed
   *   Returns JSON object if fixtures are enabled, otherwise returns FALSE.
   */
  public function getFixture() : mixed {
    if ($this->featureManager->isEnabled(FeatureManagerInterface::USE_MOCK_RESPONSES)) {
      $json = file_get_contents($this->getFixturePath(self::FIXTURE_NAME));
      if ($json) {
        return json_decode($json);
      }
    }
    return FALSE;
  }

  /**
   * Get path to fixture.
   *
   * @param string $url
   *   URL to parse.
   *
   * @return string
   *   Returns path to fixture.
   */
  protected function getFixturePath(string $url) : string {
    return vsprintf('%s/../fixtures/%s.json', [
      __DIR__,
      str_replace('/', '-', ltrim($url, '/')),
    ]);
  }

}
