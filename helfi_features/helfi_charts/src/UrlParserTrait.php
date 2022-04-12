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
   * Check that given URL has correct properties.
   *
   * @param \Psr\Http\Message\UriInterface $uri
   *   The uri from chart URL.
   */
  protected function assertMediaLink(UriInterface $uri) : void {
    if (
      $uri->getHost() === Chart::CHART_POWERBI_URL &&
      str_contains($uri->getPath(), '/view')
    ) {
      return;
    }
    throw new \InvalidArgumentException('Invalid media URL. Check URL.');
  }

  /**
   * Gets the URI object for given url.
   *
   * @param string $url
   *   The uri to parse.
   *
   * @return \Psr\Http\Message\UriInterface
   *   The uri.
   */
  protected function mediaUrlToUri(string $url) :  UriInterface {
    return Http::createFromString($url);
  }

}
