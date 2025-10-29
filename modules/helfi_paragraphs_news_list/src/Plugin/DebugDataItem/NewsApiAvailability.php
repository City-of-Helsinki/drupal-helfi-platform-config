<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_news_list\Plugin\DebugDataItem;

use Drupal\Core\DependencyInjection\AutowiredInstanceTrait;
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

  use AutowiredInstanceTrait;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EnvironmentResolverInterface $environmentResolver,
    private readonly ClientInterface $client,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
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
