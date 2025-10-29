<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_news_list\Plugin\DebugDataItem;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_api_base\Attribute\DebugDataItem;
use Drupal\helfi_api_base\Debug\SupportsValidityChecksInterface;
use Drupal\helfi_api_base\DebugDataItemPluginBase;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_api_base\Environment\ServiceEnum;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Utils;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Debug data client.
 *
 * This is used to ensure the current instance has access to the News list
 * API.
 */
#[DebugDataItem(
  id: 'news_list',
  title: new TranslatableMarkup('News list'),
)]
final class NewsApiAvailability extends DebugDataItemPluginBase implements ContainerFactoryPluginInterface, SupportsValidityChecksInterface {

  /**
   * The HTTP Client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  private ClientInterface $client;

  /**
   * The environment resolver service.
   *
   * @var \Drupal\helfi_api_base\Environment\EnvironmentResolverInterface
   */
  private EnvironmentResolverInterface $environmentResolver;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): self {
    $instance = new self($configuration, $plugin_id, $plugin_definition);
    $instance->client = $container->get(ClientInterface::class);
    $instance->environmentResolver = $container->get(EnvironmentResolverInterface::class);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function check(): bool {
    try {
      $environment = $this->environmentResolver
        ->getEnvironment(Project::ETUSIVU, $this->environmentResolver->getActiveEnvironmentName());

      $uri = $environment->getService(ServiceEnum::ElasticProxy)
        ->address
        ->getAddress();

      $content = $this->client->request('GET', $uri);
      $json = Utils::jsonDecode($content->getBody()->getContents(), TRUE);

      return !empty($json['version']);
    }
    catch (\InvalidArgumentException | GuzzleException) {
    }
    return FALSE;
  }

}
