<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_remote_video\Entity;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Bundle class for remote_video paragraph.
 */
class ParagraphRemoteVideo extends Paragraph implements ParagraphInterface {
  use StringTranslationTrait;

  /**
   * Get title of video.
   *
   */
  public function setMediaEntityIframeTitle() :void {
    if (!$this->isValid()) {
      return;
    }

    $iframe_title = $this->get('field_iframe_title')->value;
    $referenced_entities = $this->get('field_remote_video')->referencedEntities();

    if (empty($referenced_entities)) {
      return;
    }
    
    $target = reset($referenced_entities);
    $target->iframe_title = $iframe_title ? $iframe_title : t('Remote video');
  }

  /**
   * Is valid.
   *
   * @return bool
   *   Is valid.
   */
  private function isValid(): bool {
    if (
      !$this->hasField('field_remote_video') ||
      !$this->hasField('field_iframe_title') ||
      $this->get('field_remote_video')->isEmpty()
    ) {
      return FALSE;
    }

    return TRUE;
  }

}
