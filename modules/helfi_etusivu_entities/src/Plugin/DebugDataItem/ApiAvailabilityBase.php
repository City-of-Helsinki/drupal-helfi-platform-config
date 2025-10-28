<?php

declare(strict_types=1);

namespace Drupal\helfi_etusivu_entities\Plugin\DebugDataItem;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\helfi_api_base\Debug\SupportsValidityChecksInterface;
use Drupal\helfi_api_base\DebugDataItemPluginBase;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Utils;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Debug data client for Etusivu JSON:API connection.
 *
 * This is used to ensure the current instance has access to
 * API used by Etusivu entities, such as Surveys and Announcements.
 */
abstract class ApiAvailabilityBase extends DebugDataItemPluginBase implements SupportsValidityChecksInterface, ContainerFactoryPluginInterface {

  /**
   * The HTTP Client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $client;

  /**
   * The environment resolver service.
   *
   * @var \Drupal\helfi_api_base\Environment\EnvironmentResolverInterface
   */
  protected EnvironmentResolverInterface $environmentResolver;

  /**
   * {@inheritdoc}
   */
  final public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): static {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->client = $container->get(ClientInterface::class);
    $instance->environmentResolver = $container->get(EnvironmentResolverInterface::class);
    return $instance;
  }

  /**
   * Gets the base path for API request.
   *
   * @return string
   *   The base path.
   */
  abstract protected function getBasePath(): string;

  /**
   * {@inheritdoc}
   */
  public function check() : bool {
    try {
      $environment = $this->environmentResolver
        ->getEnvironment(Project::ETUSIVU, $this->environmentResolver->getActiveEnvironmentName());
    }
    catch (\InvalidArgumentException) {
      return FALSE;
    }

    $uri = vsprintf('%s/jsonapi/%s', [
      // Use internal address to bypass Varnish cache.
      $environment->getInternalAddress('fi'),
      ltrim($this->getBasePath(), '/'),
    ]);

    try {
      $content = $this->client->request('GET', $uri);
      $json = Utils::jsonDecode($content->getBody()->getContents(), TRUE);

      return !empty($json['meta']);
    }
    catch (GuzzleException) {
    }

    return FALSE;
  }

}
