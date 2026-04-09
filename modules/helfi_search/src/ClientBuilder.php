<?php

declare(strict_types=1);

namespace Drupal\helfi_search;

use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_api_base\Environment\ServiceEnum;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder as ElasticClientBuilder;

/**
 * The client builder factory.
 *
 * The test environment index is not stable enough for
 * acceptance testing the new serach. This factory creates
 * an elasticsearch client that connects to the production.
 *
 * We use this for internal testing only, this can be removed later.
 *
 * @todo remove this.
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
  public function create(int $timeout = 5, int $connectTimeout = 1) : Client {
    $environment = $this->environmentResolver
      ->getEnvironment(Project::ETUSIVU, EnvironmentEnum::Prod->value);

    $service = $environment
      ->getService(ServiceEnum::ElasticProxy)
      ->address;

    return ElasticClientBuilder::create()
      ->setSSLVerification($service->protocol === 'https')
      ->setHosts([
        $service->getAddress(),
      ])
      ->setHttpClientOptions([
        'timeout' => $timeout,
        'connect_timeout' => $connectTimeout,
      ])
      ->build();
  }

}
