<?php

declare(strict_types=1);

namespace Drupal\helfi_tpr_config\Drush\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\content_lock\ContentLock\ContentLock;
use Drush\Attributes\Command;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * A Drush command file.
 */
final class RemoveNonHelsinkiTPRUnitsCommands extends DrushCommands {

  use StringTranslationTrait;
  use AutowireTrait;

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The Entity Type Manager.
   * @param \Drupal\content_lock\ContentLock\ContentLock $contentLock
   *   The Content Lock service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'content_lock')]
    protected ContentLock $contentLock,
  ) {}

  /**
   * Removes all the TPR Unit entities which are not located in Helsinki.
   */
  #[Command(name: 'helfi:remove-non-helsinki-tpr-units')]
  public function remove() : void {
    // Get TPR Unit entities that DON'T have "Helsinki" or "Helsingfors" in
    // their 'address__locality' field.
    $entityStorage = $this->entityTypeManager->getStorage('tpr_unit');

    $unit_ids = $entityStorage->getQuery()
      ->condition('address__locality', 'Helsinki', '!=')
      ->condition('address__locality', 'Helsingfors', '!=')
      ->accessCheck(FALSE)
      ->execute();

    $unit_count = 0;

    // Delete the units.
    foreach ($unit_ids as $unit_id) {
      /** @var \Drupal\helfi_tpr\Entity\Unit $unit */
      $unit = $this->entityTypeManager->getStorage('tpr_unit')->load($unit_id);

      $this->output()->writeln((string) $this->t('Deleting "@unit_label"', ['@unit_label' => $unit->label()]));

      // Release content lock if needed.
      if ($this->contentLock->fetchLock($unit->id(), $unit->language()->getId(), entity_type: 'tpr_unit')) {
        $this->contentLock->release($unit->id(), $unit->language()->getId(), entity_type: 'tpr_unit');
      }

      // Force delete unit.
      $unit->delete(TRUE);

      $unit_count++;
    }

    $this->output()->writeln((string) $this->t('@unit_count units deleted.', ['@unit_count' => $unit_count]));
  }

}
