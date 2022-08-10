<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation\Service;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\helfi_api_base\Environment\EnvironmentResolver;
use Drupal\helfi_api_base\Environment\Project;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * Service class for global navigation related functions.
 */
class GlobalNavigationService {

  /**
   * Construct an instance.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP client.
   * @param \Drupal\helfi_api_base\Environment\EnvironmentResolver $environmentResolver
   *   EnvironmentResolver helper class.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger channel.
   */
  public function __construct(
    protected ClientInterface $httpClient,
    protected EnvironmentResolver $environmentResolver,
    protected LanguageManagerInterface $languageManager,
    protected LoggerInterface $logger,
  ) {
  }

  public function getExternalMenu(string $menuId, array $options = []) : object {
    return $this->makeRequest('GET', "/jsonapi/menu_items/$menuId", $options);
  }

  public function getMainMenu(array $options = []) : object {
    return $this->makeRequest('GET', '/api/v1/global-menu', $options);
  }

  public function updateMainMenu(array $data, string $authorization) : void {
    $endpoint = sprintf('/api/v1/global-menu/%s', $this->environmentResolver->getActiveEnvironment()->getId());
    $this->makeRequest('POST', $endpoint, [
      'headers' => [
        'Authorization' => $authorization,
      ],
      'json' => $data,
    ]);
  }

  /**
   * Makes a request based on parameters and returns the response.
   *
   * @param string $method
   *   Request method.
   * @param string $endpoint
   *   The endpoint in the instance.
   * @param array $options
   *   Body for requests.
   *
   * @return object
   *   The JSON object.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  private function makeRequest(string $method, string $endpoint, array $options = []): object {
    $activeEnvironmentName = $this->environmentResolver
      ->getActiveEnvironment()
      ->getEnvironmentName();

    $baseUrl = $this->environmentResolver
      ->getEnvironment(Project::ETUSIVU, $activeEnvironmentName)
      ->getUrl($this->languageManager->getCurrentLanguage()->getId());

    $url = sprintf('%s/%s', $baseUrl, ltrim($endpoint, '/'));

    // Disable SSL verification in local environment.
    if ($activeEnvironmentName === 'local') {
      $options['verify'] = FALSE;
    }

    try {
      $response = $this->httpClient->request($method, $url, $options);
      return \GuzzleHttp\json_decode($response->getBody()->getContents());
    }
    catch (GuzzleException | \InvalidArgumentException $e) {
      // Log the error and re-throw the exception.
      $this->logger->error('Request failed with error: ' . $e->getMessage());
      throw $e;
    }
  }

}
