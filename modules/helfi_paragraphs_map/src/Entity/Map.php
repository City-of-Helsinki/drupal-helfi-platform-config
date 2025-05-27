<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_map\Entity;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Bundle class for map paragraph.
 */
class Map extends Paragraph implements ParagraphInterface {
  use StringTranslationTrait;

  /**
   * Set the iframe title to the referenced entity in field_chart.
   */
  public function getIframeTitle(): void {
    if (!$this->isValid()) {
      return;
    }

    $iframe_title = $this->get('field_iframe_title')->value;
    $referenced_entities = $this->get('field_map_map')->referencedEntities();

    if (empty($referenced_entities)) {
      return;
    }

    $target = reset($referenced_entities);
    $target->iframe_title = $iframe_title ? $iframe_title : t('Location on map');
  }

  /**
   * Is valid.
   *
   * @return bool
   *   Is valid.
   */
  private function isValid(): bool {
    if (
      !$this->hasField('field_map_map') ||
      !$this->hasField('field_iframe_title') ||
      $this->get('field_map_map')->isEmpty()
    ) {
      return FALSE;
    }

    return TRUE;
  }

}
