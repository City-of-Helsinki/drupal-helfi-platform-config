<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config;

use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_api_base\Environment\ServiceEnum;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder as ElasticClientBuilder;

/**
 * The client builder factory.
 */
final class ClientBuilder {

  public function __construct(
    private readonly EnvironmentResolverInterface $environmentResolver,
  ) {
  }

  /**
   * Creates a new client instance.
   *
   * @return \Elastic\Elasticsearch\Client
   *   The client.
   */
  public function create(int $timeout = 1, int $connectTimeout = 1) : Client {
    try {
      $environment = $this->environmentResolver
        ->getEnvironment(Project::ETUSIVU, $this->environmentResolver->getActiveEnvironmentName());
    }
    catch (\InvalidArgumentException) {
      // Use prod in case a matching environment does not exist.
      $environment = $this->environmentResolver
        ->getEnvironment(Project::ETUSIVU, EnvironmentEnum::Prod->value);
    }

    $service = $environment
      ->getService(ServiceEnum::ElasticProxy)
      ->address;

    return ElasticClientBuilder::create()
      ->setSSLVerification($service->protocol === 'https')
      ->setHosts([
        $service->getAddress(),
      ])
      ->setHttpClientOptions([
        'client' => [
          'timeout' => $timeout,
          'connect_timeout' => $connectTimeout,
        ],
      ])
      ->build();
  }

}
