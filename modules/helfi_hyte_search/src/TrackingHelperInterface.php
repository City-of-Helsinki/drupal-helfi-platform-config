<?php

declare(strict_types=1);

namespace Drupal\helfi_hyte_search;

use Drupal\Core\Entity\EntityInterface;
use Drupal\helfi_tpr\Entity\Channel;
use Drupal\helfi_tpr\Entity\ErrandService;
use Drupal\helfi_tpr\Entity\Unit;

/**
 * Provides an interface for the tracking helper service.
 */
interface TrackingHelperInterface {

  /**
   * Reacts to a tpr entity being inserted, updated or deleted.
   *
   * Forwards call to a proper tracking method.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity that just got changed (inserted, updated or deleted).
   * @param bool $new
   *   TRUE if the entity was inserted, FALSE if it was updated or deleted.
   *   Defaults to FALSE.
   */
  public function trackTprUpdate(EntityInterface $entity, bool $new = FALSE);

  /**
   * Reacts to a tpr_service_channel entity being inserted, updated or deleted.
   *
   * Marks all referenced tpr_service entities as updated.
   *
   * @param \Drupal\helfi_tpr\Entity\Channel $channel
   *   The channel that just got changed (inserted, updated or deleted).
   */
  public function trackChannelUpdate(Channel $channel);

  /**
   * Reacts to a tpr_errand_service entity being updated or deleted.
   *
   * Marks all referencing tpr_service entities as updated.
   *
   * @param \Drupal\helfi_tpr\Entity\ErrandService $errandService
   *   The errand service that just got changed (updated or deleted).
   */
  public function trackErrandServiceUpdate(ErrandService $errandService);

  /**
   * Reacts to a tpr_unit entity being updated or deleted.
   *
   * Marks all referencing tpr_errand_service entities as updated.
   *
   * @param \Drupal\helfi_tpr\Entity\Unit $unit
   *   The unit that just got changed (updated or deleted).
   */
  public function trackUnitUpdate(Unit $unit);

}
