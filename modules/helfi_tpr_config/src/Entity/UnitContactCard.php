<?php

declare(strict_types=1);

namespace Drupal\helfi_tpr_config\Entity;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Bundle class for unit_contact_card paragraph.
 */
class UnitContactCard extends Paragraph implements ParagraphInterface {

  /**
   * Get aria-label for contact card link.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   String to be used as aria-label for contact card link.
   */
  public function getAriaLabel(): ?TranslatableMarkup {
    if ($this->hasField('field_unit_contact_unit')) {
      $unit = $this->get('field_unit_contact_unit')->entity;
      if ($unit && $unit->hasField('name_override')) {
        $unit_name = $unit->get('name_override')->value;
        if ($unit_name) {
          return t('See more details of @unit', [
            '@unit' => $unit_name,
          ], [
            'context' => 'Unit contact card aria label',
          ]);
        }
      }
    }
    return NULL;
  }

}
