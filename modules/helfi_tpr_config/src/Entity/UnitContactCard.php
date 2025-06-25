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
    $langcode = $this->language()->getId();
    if (!$this->hasField('field_unit_contact_unit')) {
      return NULL;
    }

    /** @var \Drupal\Core\Entity\ContentEntityInterface $unit */
    $unit = $this->get('field_unit_contact_unit')->entity;
    if (!$unit->hasTranslation($langcode)) {
      return NULL;
    }

    $unit = $unit->getTranslation($langcode);
    if ($unit instanceof Unit && $unit->hasField('name_override') && $unit->hasField('name')) {
      if (($langcode === 'sv' || $langcode === 'en') && $unit->hasTranslation($langcode)) {
        $unit_name = $unit->get('name')->value;
      }
      else {
        $unit_name = $unit->get('name_override')->value;
      }
      if ($unit_name) {
        return $this->t('See more details of @unit', [
          '@unit' => $unit_name,
        ], [
          'context' => 'Unit contact card aria label',
        ]);
      }
    }
    return NULL;
  }

}
