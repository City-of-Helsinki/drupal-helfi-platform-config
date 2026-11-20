<?php

declare(strict_types=1);

namespace Drupal\helfi_media_map;

use Drupal\helfi_media_map\Plugin\media\Source\Map;
use League\Uri\Http;
use Psr\Http\Message\UriInterface;

/**
 * Url parser helper.
 */
trait UrlParserTrait {

  /**
   * The languages supported by the map services.
   */
  private const array LANGUAGES = ['fi', 'sv', 'en'];

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
   * @return string
   *   The url.
   */
  protected function getEmbedUrl(string $uri) : string {
    $uri = Http::new($uri);

    $this->assertMediaLink($uri);

    return (string) match ($uri->getHost()) {
      Map::KARTTA_URL => $this->getKarttaHelfiEmbedUrl($uri),
      Map::PALVELUKARTTA_URL => $this->getPalvelukarttaEmbedUrl($uri),
      // The embed path is always /{language}/embed. Everything else, like
      // the sport, city and unit filters, is in query parameters.
      Map::ULKOLIIKUNTA_URL => $uri->withPath('/' . $this->getUlkoliikuntaLanguage($uri) . '/embed'),
      default => throw new \LogicException('Invalid url.'),
    };
  }

  /**
   * Gets the kartta.hel.fi embed URL.
   *
   * @param \Psr\Http\Message\UriInterface $uri
   *   The uri.
   *
   * @return \Psr\Http\Message\UriInterface
   *   The embed uri, like /embed?link={id}.
   */
  private function getKarttaHelfiEmbedUrl(UriInterface $uri) : UriInterface {
    if (str_contains($uri->getPath(), 'embed')) {
      return $uri;
    }
    // Everything else than the short link ID (/link/{id}) is in query
    // parameters already.
    [$prefix, $id] = $this->getPathParts($uri) + [NULL, NULL];
    $uri = $uri->withPath('/embed');

    return $prefix === 'link' && $id ? $uri->withQuery("link=$id") : $uri;
  }

  /**
   * Gets the palvelukartta.hel.fi embed URL.
   *
   * @param \Psr\Http\Message\UriInterface $uri
   *   The uri.
   *
   * @return \Psr\Http\Message\UriInterface
   *   The embed uri, like /{language}/embed/{rest_of_the_path}.
   */
  private function getPalvelukarttaEmbedUrl(UriInterface $uri) : UriInterface {
    if (str_contains($uri->getPath(), '/embed')) {
      return $uri;
    }
    // Fall back to Finnish if the path is empty.
    $parts = $this->getPathParts($uri) ?: ['fi'];
    $language = in_array($parts[0], self::LANGUAGES, TRUE) ? [array_shift($parts)] : [];

    return $uri->withPath('/' . implode('/', [...$language, 'embed', ...$parts]));
  }

  /**
   * Parses 'Open map in new window' link from embed URL.
   *
   * @param string $uri
   *   The uri from embed URL.
   *
   * @return string
   *   The url.
   */
  protected function getMapUrl(string $uri) : string {
    $uri = Http::new($uri);

    $this->assertMediaLink($uri);

    return (string) match ($uri->getHost()) {
      // The map links are the embed links without /embed.
      Map::KARTTA_URL, Map::PALVELUKARTTA_URL => $uri->withPath('/' . ltrim(str_replace('/embed', '', $uri->getPath()), '/')),
      Map::ULKOLIIKUNTA_URL => $this->getUlkoliikuntaMapUrl($uri),
      default => throw new \LogicException('Invalid url.'),
    };
  }

  /**
   * Gets the ulkoliikunta.fi map URL.
   *
   * The embed filters don't work outside the embed, so this links to the unit
   * page if the embed has a unit, and to the front page otherwise.
   *
   * @param \Psr\Http\Message\UriInterface $uri
   *   The uri.
   *
   * @return \Psr\Http\Message\UriInterface
   *   The map uri, like /{language}/unit/{unit} or /{language}.
   */
  private function getUlkoliikuntaMapUrl(UriInterface $uri) : UriInterface {
    parse_str($uri->getQuery(), $query);
    $path = '/' . $this->getUlkoliikuntaLanguage($uri);

    if (is_string($query['unit'] ?? NULL) && ctype_digit($query['unit'])) {
      $path .= '/unit/' . $query['unit'];
    }
    return $uri->withPath($path)->withQuery('');
  }

  /**
   * Gets the language of an ulkoliikunta.fi URL.
   *
   * @param \Psr\Http\Message\UriInterface $uri
   *   The uri.
   *
   * @return string
   *   The language from the URL path, or 'fi' if the path has no language.
   */
  private function getUlkoliikuntaLanguage(UriInterface $uri) : string {
    $language = $this->getPathParts($uri)[0] ?? NULL;
    return in_array($language, self::LANGUAGES, TRUE) ? $language : 'fi';
  }

  /**
   * Gets the non-empty path segments of the uri.
   *
   * @param \Psr\Http\Message\UriInterface $uri
   *   The uri.
   *
   * @return string[]
   *   The path segments.
   */
  private function getPathParts(UriInterface $uri) : array {
    return array_values(array_filter(explode('/', $uri->getPath())));
  }

}
