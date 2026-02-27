<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Pipeline;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Masterminds\HTML5;

/**
 * Extracts HTML from an entity's canonical URL via HTTP request.
 *
 * Making an HTTP GET to the entity's canonical URL means the HTML generation
 * goes through the full Drupal rendering pipeline.
 *
 * Varnish caching should make this efficient for already-cached pages.
 */
class HtmlExtractor {

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
   * @throws \Drupal\helfi_search\Pipeline\PipelineException
   *   When the HTTP request fails.
   */
  public function extract(EntityInterface $entity): \DOMDocument {
    try {
      $url = $entity->toUrl('canonical', ['absolute' => TRUE])->toString();
    }
    catch (EntityMalformedException $e) {
      throw new PipelineException($e->getMessage(), previous: $e);
    }

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
      throw new PipelineException($e->getMessage(), previous: $e);
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
