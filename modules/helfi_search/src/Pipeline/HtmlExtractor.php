<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Pipeline;

use Drupal\Core\Entity\EntityInterface;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Masterminds\HTML5;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Extracts HTML from an entity's canonical URL via HTTP request.
 *
 * Making an HTTP GET to the entity's canonical URL means the HTML generation
 * goes through the full Drupal rendering pipeline.
 *
 * Varnish caching should make this efficient for already-cached pages.
 */
class HtmlExtractor implements LoggerAwareInterface {

  use LoggerAwareTrait;

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly EnvironmentResolverInterface $environmentResolver,
  ) {
  }

  /**
   * Extract HTML from the entity's canonical URL.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to extract HTML from.
   *
   * @return \DOMDocument
   *   Parsed HTML document.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   When the HTTP request fails.
   */
  public function extract(EntityInterface $entity): \DOMDocument {
    $url = $entity->toUrl('canonical', ['absolute' => TRUE])->toString();

    try {
      $response = $this->httpClient->request('GET', $url, [
        'headers' => [
          'User-Agent' => 'Helfi-Search/1.0',
        ],
        'verify' => $this->getVerify(),
      ]);

      $html5 = new HTML5([
        'disable_html_ns' => TRUE,
        'encoding' => 'UTF-8',
      ]);

      return $html5->loadHTML((string) $response->getBody());
    }
    catch (GuzzleException $e) {
      $this->logger?->error('Failed to extract HTML from @url: @message', [
        '@url' => $url,
        '@message' => $e->getMessage(),
      ]);
      throw $e;
    }
  }

  /**
   * Return true if the server certificate should be verified.
   */
  private function getVerify(): bool {
    try {
      // Disable certificate verification on local environments.
      return $this->environmentResolver->getActiveEnvironmentName() != EnvironmentEnum::Local->value;
    }
    catch (\InvalidArgumentException) {
    }

    return TRUE;
  }

}
