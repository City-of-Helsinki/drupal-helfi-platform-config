<?php

declare(strict_types=1);

namespace Drupal\helfi_etusivu_entities\Plugin\DebugDataItem;

use Drupal\Core\DependencyInjection\AutowiredInstanceTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\external_entities\ExternalEntityStorageInterface;
use Drupal\helfi_api_base\Debug\SupportsValidityChecksInterface;
use Drupal\helfi_api_base\DebugDataItemPluginBase;
use Drupal\helfi_etusivu_entities\Plugin\ExternalEntities\StorageClient\EtusivuJsonApiEntityBase;

/**
 * Debug data client for Etusivu JSON:API connection.
 *
 * This is used to ensure the current instance has access to
 * the API used by Etusivu entities.
 */
abstract class ApiAvailabilityBase extends DebugDataItemPluginBase implements SupportsValidityChecksInterface, ContainerFactoryPluginInterface {

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
   * Gets the entity type to check.
   *
   * @return string
   *   The entity type.
   */
  abstract protected function getEntityTypeId(): string;

  /**
   * {@inheritdoc}
   */
  public function check() : bool {
    $storage = $this->entityTypeManager->getStorage($this->getEntityTypeId());
    assert($storage instanceof ExternalEntityStorageInterface);

    $client = $storage->getExternalEntityType()
      ->getDataAggregator()
      ->getStorageClient(0);

    assert($client instanceof EtusivuJsonApiEntityBase);

    return $client->ping();
  }

}
