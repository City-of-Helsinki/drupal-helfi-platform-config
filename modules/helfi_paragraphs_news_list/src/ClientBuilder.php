<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_news_list;

use Drupal\helfi_api_base\Environment\Environment;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_api_base\Environment\ServiceEnum;
use Elasticsearch\Client;
use Elasticsearch\ClientBuilder as ElasticClientBuilder;

/**
 * The client builder factory.
 */
final class ClientBuilder {

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
   * @return \Elasticsearch\Client
   *   The client.
   */
  public function create() : Client {
    try {
      $environment = $this->environmentResolver
        ->getEnvironment(Project::ETUSIVU, $this->environmentResolver->getActiveEnvironmentName());
    }
    catch (\InvalidArgumentException) {
      $environment = $this->environmentResolver
        ->getEnvironment(Project::ETUSIVU, EnvironmentEnum::Test->value);
    }
    $service = $environment
      ->getService(ServiceEnum::ElasticProxy)
      ->address;

    return ElasticClientBuilder::create()
      ->setSSLVerification($service->protocol === 'https')
      ->setHosts([
        [
          'host' => $service->domain,
          'port' => $service->port,
          'scheme' => $service->protocol,
        ],
      ])
      ->setConnectionParams([
        'client' => [
          'timeout' => 1,
          'connect_timeout' => 1,
        ],
      ])
      ->build();
  }

}
