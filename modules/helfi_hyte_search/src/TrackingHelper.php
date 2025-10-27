<?php

declare(strict_types=1);

namespace Drupal\helfi_hyte_search;

use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\helfi_tpr\Entity\Channel;
use Drupal\helfi_tpr\Entity\ErrandService;
use Drupal\helfi_tpr\Entity\Unit;
use Drupal\helfi_tpr\Entity\Service;
use Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager;

/**
 * Provides a tracking helper service.
 */
class TrackingHelper implements TrackingHelperInterface {

  /**
   * Service constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function trackTprUpdate(EntityInterface $entity, bool $new = FALSE) {
    if ($entity instanceof Channel && !$new) {
      $this->trackChannelUpdate($entity);
    }
    elseif ($entity instanceof ErrandService) {
      $this->trackErrandServiceUpdate($entity);
    }
    elseif ($entity instanceof Unit && !$new) {
      $this->trackUnitUpdate($entity);
    }
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\helfi_react_search\Plugin\search_api\processor\ChannelsForService::addFieldValues()
   */
  public function trackChannelUpdate(Channel $channel) {
    // Channel data is indexed with tpr services in hyte index.
    // The relation is service -> errand_service -> channel, so we need to
    // travel that path backwards and track all errand services that have a
    // relation to this channel.
    $errandServiceIds = $this->entityTypeManager->getStorage('tpr_errand_service')->getQuery()
      ->accessCheck(FALSE)
      ->condition('channels', $channel->id())
      ->execute();

    if (empty($errandServiceIds)) {
      return;
    }

    foreach ($errandServiceIds as $errandServiceId) {
      $errandService = $this->entityTypeManager->getStorage('tpr_errand_service')->load($errandServiceId);

      if (!$errandService || !$errandService instanceof ErrandService) {
        continue;
      }

      $this->trackErrandServiceUpdate($errandService);
    }
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\helfi_react_search\Plugin\search_api\processor\ChannelsForService::addFieldValues()
   */
  public function trackErrandServiceUpdate(ErrandService $errandService) {
    // Channel data is indexed through errand services with tpr services in
    // hyte index. The relation is service -> errand_service -> channel, so we
    // need to travel that path backwards and reindex all services that
    // have a relation to this errand service.
    $serviceIds = $this->entityTypeManager->getStorage('tpr_service')->getQuery()
      ->accessCheck(FALSE)
      ->condition('errand_services', $errandService->id())
      ->execute();

    if (empty($serviceIds)) {
      return;
    }

    $services = $this->entityTypeManager->getStorage('tpr_service')->loadMultiple($serviceIds);
    $this->updateIndexedItems($services);
  }

  /**
   * {@inheritdoc}
   */
  public function trackUnitUpdate(Unit $unit) {
    // Unit data is indexed with tpr services in hyte index, so we need to
    // reindex all services this Unit relates to.
    $services_field = $unit->get('services');
    assert($services_field instanceof EntityReferenceFieldItemListInterface);
    $services = $services_field->referencedEntities();
    $this->updateIndexedItems($services);
  }

  /**
   * Updates the indexed items for a tpr service.
   *
   * @param \Drupal\helfi_tpr\Entity\Service[] $services
   *   The services that just got updated.
   */
  private function updateIndexedItems(array $services) {
    if (empty($services)) {
      return;
    }

    /** @var \Drupal\search_api\IndexInterface $index */
    $index = $this->entityTypeManager->getStorage('search_api_index')->load('hyte');
    if (!$index) {
      return;
    }

    foreach ($index->getDatasources() as $datasource_id => $datasource) {
      $item_ids = [];

      if (!$datasource->canContainEntityReferences()) {
        continue;
      }

      foreach ($services as $service) {
        if (!$service instanceof Service) {
          continue;
        }

        foreach (array_keys($service->getTranslationLanguages()) as $langcode) {
          $item_ids[] = ContentEntityTrackingManager::formatItemId($service->getEntityTypeId(), $service->id(), $langcode);
        }
      }

      if (!empty($item_ids)) {
        $index->trackItemsUpdated($datasource_id, $item_ids);
      }
    }
  }

}
