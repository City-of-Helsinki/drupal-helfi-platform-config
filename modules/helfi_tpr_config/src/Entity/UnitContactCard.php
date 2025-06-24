<?php

declare(strict_types=1);

namespace Drupal\helfi_tpr_config\Entity;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Bundle class for unit_contact_card paragraph.
 */
class UnitContactCard extends Paragraph implements ParagraphInterface {

  /**
   * The language manager.
   */
  private LanguageManagerInterface $languageManager;

  /**
   * Get aria-label for contact card link.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   String to be used as aria-label for contact card link.
   */
  public function getAriaLabel(): ?TranslatableMarkup {
    if ($this->hasField('field_unit_contact_unit')) {
      $unit = $this->get('field_unit_contact_unit')->entity;
      $langcode = $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
      if ($unit instanceof Unit && $unit->hasField('name_override') && $unit->hasField('name')) {
        if (($langcode === 'sv' || $langcode === 'en') && $unit->hasTranslation($langcode)) {
          $translated_unit = $unit->getTranslation($langcode);
          $unit_name = $translated_unit->get('name')->value;
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
    }
    return NULL;
  }

}
