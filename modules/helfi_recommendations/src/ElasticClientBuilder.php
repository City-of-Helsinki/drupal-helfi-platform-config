<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations;

use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_api_base\Environment\ServiceEnum;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;

/**
 * The client builder factory.
 */
final class ElasticClientBuilder {

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\helfi_api_base\Environment\EnvironmentResolverInterface $environmentResolver
   *   The environment resolver.
   */
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
  public function create() : Client {
    try {
      $environment = $this->environmentResolver
        ->getEnvironment(Project::ETUSIVU, $this->environmentResolver->getActiveEnvironmentName());
    }
    catch (\InvalidArgumentException) {
      // Use prod in case matching environment does not exist.
      $environment = $this->environmentResolver
        ->getEnvironment(Project::ETUSIVU, EnvironmentEnum::Prod->value);
    }
    $service = $environment
      ->getService(ServiceEnum::ElasticProxy)
      ->address;

    return ClientBuilder::create()
      ->setSSLVerification($service->protocol === 'https')
      ->setHosts([
        $service->getAddress(),
      ])
      ->setHttpClientOptions([
        'client' => [
          'timeout' => 1,
          'connect_timeout' => 1,
        ],
      ])
      ->build();
  }

}
