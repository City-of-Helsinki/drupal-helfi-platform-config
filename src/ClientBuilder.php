<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config;

use Drupal\Core\Http\ClientFactory;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_api_base\Environment\ServiceEnum;
use Drupal\helfi_api_base\Vault\VaultManager;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder as ElasticClientBuilder;

/**
 * The client builder factory.
 */
final readonly class ClientBuilder {

  public function __construct(
    private EnvironmentResolverInterface $environmentResolver,
    private VaultManager $vaultManager,
    private ClientFactory $httpClientFactory,
  ) {
  }

  /**
   * Creates a new client instance.
   *
   * @return \Elastic\Elasticsearch\Client
   *   The client.
   */
  public function create(int $timeout = 5, int $connectTimeout = 1) : Client {
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
      ->getService(ServiceEnum::Elastic)
      ->address;

    $client = ElasticClientBuilder::create()
      ->setHosts([
        $service->getAddress(),
      ])
      ->setHttpClient($this->httpClientFactory->fromOptions([
        'timeout' => $timeout,
        'connect_timeout' => $connectTimeout,
        // Elasticsearch uses a self-signed certificate.
        'verify' => FALSE,
      ]))
      ->build();

    if ($token = $this->vaultManager->get('etusivu_elastic')) {
      $client->getTransport()
        ->setHeader('Authorization', 'Basic ' . $token->data());
    }

    return $client;
  }

}
