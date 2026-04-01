<?php

declare(strict_types=1);

namespace Drupal\helfi_media_remote_video;

use Drupal\Core\DependencyInjection\AutowireTrait;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Resolves Terveyskylä URN URLs to player embed URLs.
 *
 * Terveyskylä URLs (urn.terveyskyla.fi/media/{id}) redirect (301) to player
 * embed URLs. The media ID differs from the asset ID, so the redirect must be
 * followed. Only the first redirect is used.
 */
class TerveyskylaUrlResolver {

  use AutowireTrait;

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Determines whether a URL is a Terveyskylä URN.
   */
  public function isTerveyskylaUrl(string $url): bool {
    return str_contains($url, 'urn.terveyskyla.fi/media/');
  }

  /**
   * Resolves a Terveyskylä URN to a player embed URL.
   *
   * Makes an HTTP HEAD request and captures the Location header from the
   * initial redirect response.
   *
   * @param string $url
   *   The Terveyskylä video URL.
   *
   * @return string|null
   *   The resolved player URL, or NULL on failure.
   */
  public function resolve(string $url): ?string {
    try {
      $response = $this->httpClient->request('HEAD', $url, [
        'allow_redirects' => FALSE,
        'timeout' => 5,
      ]);

      $location = $response->getHeaderLine('Location');

      return $location ?: NULL;
    }
    catch (\Exception $e) {
      $this->logger->info('Could not resolve Terveyskylä URL @url: @message', [
        '@url' => $url,
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

}
