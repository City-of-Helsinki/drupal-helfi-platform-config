<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_chart\Entity;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Bundle class for chart paragraph.
 */
class Chart extends Paragraph implements ParagraphInterface {

  /**
   * Set the iframe title to the referenced entity in field_chart.
   */
  public function setMediaEntityIframeTitle(): void {
    if (!$this->isValid()) {
      return;
    }

    $iframe_title = $this->get('field_iframe_title')->value;
    $referenced_entities = $this->get('field_chart_chart')->referencedEntities();

    if (empty($referenced_entities)) {
      return;
    }

    $target = reset($referenced_entities);
    $target->iframeTitle = $iframe_title ? $iframe_title : $this->t('Data chart');
  }

  /**
   * Is valid.
   *
   * @return bool
   *   Is valid.
   */
  private function isValid(): bool {
    if (
      !$this->hasField('field_chart_chart') ||
      !$this->hasField('field_iframe_title') ||
      $this->get('field_chart_chart')->isEmpty()
    ) {
      return FALSE;
    }

    return TRUE;
  }

}
