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
    // This should never happen since URLs are validated on save already, but
    // lets make sure.
    if (!in_array($uri->getHost(), Chart::VALID_URLS)) {
      throw new \InvalidArgumentException('Invalid domain, Check URL.');
    }
  }

  /**
   * Parses 'Open map in new window' link from embed URL.
   *
   * @param string $uri
   *   The uri from embed URL.
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

}
