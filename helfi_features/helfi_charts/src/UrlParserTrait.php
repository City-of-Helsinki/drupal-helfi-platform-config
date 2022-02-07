<?php

declare(strict_types = 1);

namespace Drupal\helfi_charts;

use Drupal\helfi_charts\Plugin\media\Source\Chart;
use League\Uri\Http;
use Psr\Http\Message\UriInterface;

/**
 * Url parser helper.
 */
trait UrlParserTrait {

  /**
   * Validates media link.
   *
   * @param \Psr\Http\Message\UriInterface $uri
   *   The uri.
   */
  private function assertMediaLink(UriInterface $uri) : void {
    if (!in_array($uri->getHost(), Chart::VALID_URLS)) {
      throw new \InvalidArgumentException('Invalid domain, Check URL.');
    }
  }

  /**
   * Check that given URL has correct properties.
   *
   * @param string $uri
   *   The uri from chart URL.
   *
   * @return string|null
   *   The url.
   */
  protected function getChartUrl(string $uri) : ? string {
    $uri = Http::createFromString($uri);

    $this->assertMediaLink($uri);

    if ($uri->getHost() === Chart::CHART_POWERBI_URL) {
      if (str_contains($uri->getPath(), '/view')) {
        return (string) $uri;
      }
    }
    throw new \LogicException('Invalid URL parameters. Check URL.');
  }

  /**
   * Get given URL domain.
   *
   * @param string $uri
   *   The uri from chart URL.
   *
   * @return string|null
   *   The domain in format https://sub.domain.com.
   */
  protected function getDomain(string $uri) : ? string {
    $uri = Http::createFromString($uri);
    return ($uri->getHost()) ? $uri->getHost() : NULL;
  }

}
