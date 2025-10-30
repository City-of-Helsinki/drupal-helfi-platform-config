<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_news_list\Plugin\DebugDataItem;

use Drupal\Core\DependencyInjection\AutowiredInstanceTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\external_entities\ExternalEntityStorageInterface;
use Drupal\helfi_api_base\Attribute\DebugDataItem;
use Drupal\helfi_api_base\Debug\SupportsValidityChecksInterface;
use Drupal\helfi_api_base\DebugDataItemPluginBase;
use Drupal\helfi_paragraphs_news_list\ElasticExternalEntityBase;

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

  public const array ENTITY_TYPES = [
    'helfi_news',
    'helfi_news_groups',
    'helfi_news_neighbourhoods',
    'helfi_news_tags',
  ];

  use AutowiredInstanceTrait;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function check(): bool {
    foreach (self::ENTITY_TYPES as $entityType) {
      $storage = $this->entityTypeManager->getStorage($entityType);
      assert($storage instanceof ExternalEntityStorageInterface);

      $client = $storage->getExternalEntityType()
        ->getDataAggregator()
        ->getStorageClient(0);

      assert($client instanceof ElasticExternalEntityBase);

      if (!$client->ping()) {
        return FALSE;
      }
    }
    return TRUE;
  }

}
