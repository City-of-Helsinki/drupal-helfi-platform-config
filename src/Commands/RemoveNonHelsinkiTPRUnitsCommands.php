<?php

declare(strict_types = 1);

namespace Drupal\helfi_platform_config\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Commands\DrushCommands;

/**
 * A Drush command file.
 */
final class RemoveNonHelsinkiTPRUnitsCommands extends DrushCommands {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Removes all the TPR Unit entities which are not located in Helsinki.
   *
   * @command helfi:remove-non-helsinki-tpr-units
   */
  public function remove() : void {
    // Get TPR Unit entities that DON'T have "Helsinki" or "Helsingfors" in
    // their 'address__locality' field.
    $entityStorage = $this->entityTypeManager->getStorage('tpr_unit');

    $unit_ids = $entityStorage->getQuery()
      ->condition('address__locality', 'Helsinki', '!=')
      ->condition('address__locality', 'Helsingfors', '!=')
      ->execute();

    // Set up content lock service.
    $lock_service = \Drupal::service('content_lock');

    $unit_count = 0;

    // Delete the units.
    foreach ($unit_ids as $unit_id) {
      $unit = $this->entityTypeManager->getStorage('tpr_unit')->load($unit_id);

      \Drupal::messenger()->addMessage('Deleting "' . $unit->label() . '"');

      // Release content lock if needed.
      if ($lock_service->fetchLock($unit->id(), $unit->language()->getId(), NULL, 'tpr_unit')) {
        $lock_service->release($unit->id(), $unit->language()->getId(), NULL, NULL, 'tpr_unit');
      }

      // Force delete unit.
      $unit->delete(TRUE);

      $unit_count++;
    }

    \Drupal::messenger()->addMessage($unit_count . ' units deleted.');
  }

}
