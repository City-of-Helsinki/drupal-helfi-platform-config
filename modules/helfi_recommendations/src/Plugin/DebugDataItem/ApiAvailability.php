<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations\Plugin\DebugDataItem;

use Drupal\Core\DependencyInjection\AutowiredInstanceTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_api_base\Attribute\DebugDataItem;
use Drupal\helfi_api_base\Debug\SupportsValidityChecksInterface;
use Drupal\helfi_api_base\DebugDataItemPluginBase;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ElasticsearchException;
use Elastic\Transport\Exception\NoNodeAvailableException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Utils;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Debug data client.
 *
 * This is used to ensure the current instance has access to the Recommendations
 * API.
 */
#[DebugDataItem(
  id: 'recommendations',
  title: new TranslatableMarkup('Recommendations'),
)]
final class ApiAvailability extends DebugDataItemPluginBase implements ContainerFactoryPluginInterface, SupportsValidityChecksInterface {

  use AutowiredInstanceTrait;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    #[Autowire(service: 'helfi_platform_config.etusivu_elastic_client')]
    private readonly Client $client,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function check() : bool {
    try {
      $info = $this->client->info();
      $data = Utils::jsonDecode($info->getBody()->getContents(), TRUE);

      return !empty($data['version']);
    }
    catch (NoNodeAvailableException | GuzzleException | ElasticsearchException) {
    }
    return FALSE;
  }

}
