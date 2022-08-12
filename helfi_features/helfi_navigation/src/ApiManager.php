<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation;

use Drupal\helfi_api_base\Environment\EnvironmentResolver;
use Drupal\helfi_api_base\Environment\Project;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * Service class for global navigation related functions.
 */
final class ApiManager {

  /**
   * Construct an instance.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP client.
   * @param \Drupal\helfi_api_base\Environment\EnvironmentResolver $environmentResolver
   *   EnvironmentResolver helper class.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger channel.
   */
  public function __construct(
    private ClientInterface $httpClient,
    private EnvironmentResolver $environmentResolver,
    private LoggerInterface $logger,
  ) {
  }

  /**
   * Makes a request to fetch external menu from Etusivu instance.
   *
   * @param string $langcode
   *   The langcode.
   * @param string $menuId
   *   The menu id to get.
   * @param array $options
   *   The request options.
   *
   * @return object
   *   The JSON object representing external menu.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getExternalMenu(string $langcode, string $menuId, array $options = []) : object {
    return $this->makeRequest('GET', "/jsonapi/menu_items/$menuId", $langcode, $options);
  }

  /**
   * Makes a request to fetch main menu from Etusivu instance.
   *
   * @param string $langcode
   *   The langcode.
   * @param array $options
   *   The request options.
   *
   * @return object
   *   The JSON object representing main menu.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getMainMenu(string $langcode, array $options = []) : object {
    return $this->makeRequest('GET', '/api/v1/global-menu', $langcode, $options);
  }

  /**
   * Updates the main menu for currently active project.
   *
   * @param string $langcode
   *   The langcode.
   * @param string $authorization
   *   The authorization header.
   * @param array $data
   *   The JSON data to update.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function updateMainMenu(string $langcode, string $authorization, array $data) : void {
    $endpoint = sprintf('/api/v1/global-menu/%s', $this->environmentResolver->getActiveEnvironment()->getId());
    $this->makeRequest('POST', $endpoint, $langcode, [
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
   * @param string $langcode
   *   The langcode.
   * @param array $options
   *   Body for requests.
   *
   * @return object
   *   The JSON object.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  private function makeRequest(string $method, string $endpoint, string $langcode, array $options = []): object {
    $activeEnvironmentName = $this->environmentResolver
      ->getActiveEnvironment()
      ->getEnvironmentName();

    $baseUrl = $this->environmentResolver
      ->getEnvironment(Project::ETUSIVU, $activeEnvironmentName)
      ->getUrl($langcode);

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
