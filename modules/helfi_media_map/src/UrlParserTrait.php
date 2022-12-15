<?php

declare(strict_types = 1);

namespace Drupal\helfi_media_map;

use Drupal\helfi_media_map\Plugin\media\Source\Map;
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
    if (!in_array($uri->getHost(), Map::VALID_URLS)) {
      throw new \InvalidArgumentException('Invalid domain.');
    }
  }

  /**
   * Parses 'embed' link from regular URL.
   *
   * @param string $uri
   *   The uri from map link.
   *
   * @return string|null
   *   The url.
   */
  protected function getEmbedUrl(string $uri) : string {
    $uri = Http::createFromString($uri);

    $this->assertMediaLink($uri);

    if ($uri->getHost() === Map::KARTTA_URL) {
      // Link is already a valid embed link.
      if (strpos($uri->getPath(), 'embed') !== FALSE) {
        return (string) $uri;
      }
      // Parse the url prefix (/link) and the ID /link/{id}, fallback to NULL.
      [$prefix, $id] = array_values(array_filter(explode('/', $uri->getPath()))) + [
        NULL,
        NULL,
      ];
      // We can just replace path with /embed since everything
      // important is in query arguments.
      $uri = $uri->withPath('/embed');

      if ($prefix === 'link' && $id) {
        $uri = $uri->withQuery("link=$id");
      }
      return (string) $uri;
    }

    if ($uri->getHost() === Map::PALVELUKARTTA_URL) {
      // Link is already a valid embed link.
      if (strpos($uri->getPath(), '/embed') !== FALSE) {
        return (string) $uri;
      }

      // Always fallback to finnish if path has no language.
      if ($uri->getPath() === '/') {
        $uri = $uri->withPath('/fi');
      }
      // Filter out empty array items and re-key the array so language
      // is always at index 0.
      [$language] = $path_parts = array_values(array_filter(explode('/', $uri->getPath())));

      $parts = [];
      if (in_array($language, ['fi', 'sv', 'en'])) {
        $parts[] = $language;
        array_shift($path_parts);
      }
      $parts[] = 'embed';

      // Re-construct the path so we always have a path like:
      // /{language}/embed/{rest_of_the_path}.
      return (string) $uri->withPath('/' . implode('/', array_merge($parts, $path_parts)));
    }
    throw new \LogicException('Invalid url.');
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
  protected function getMapUrl(string $uri) : ? string {
    $uri = Http::createFromString($uri);

    $this->assertMediaLink($uri);

    if ($uri->getHost() === Map::KARTTA_URL) {
      // Link is already a direct map link.
      if (strpos($uri->getPath(), 'embed') === FALSE) {
        return (string) $uri;
      }
      $path = ltrim(str_replace('/embed', '', $uri->getPath()), '/');
      return (string) $uri->withPath('/' . $path);
    }

    if ($uri->getHost() === Map::PALVELUKARTTA_URL) {
      $path = ltrim(str_replace('/embed', '', $uri->getPath()), '/');
      return (string) $uri->withPath('/' . $path);
    }
    return NULL;
  }

}
