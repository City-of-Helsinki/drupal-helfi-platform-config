<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_remote_video\Entity;

use Drupal\helfi_media_remote_video\Entity\RemoteVideo;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Bundle class for remote_video paragraph.
 */
class ParagraphRemoteVideo extends Paragraph implements ParagraphInterface {

  /**
   * Get title of video.
   */
  public function setMediaEntityIframeTitle() :void {
    if (!$this->isValid()) {
      return;
    }

    $media = $this->getReferencedMediaEntity();
    if ($media instanceof RemoteVideo) {
      $media->iframeTitle = $this->get('field_iframe_title')->value ?? $this->t('Remote video');
    }
  }

  /**
   * Checks whether the referenced media is hidden.
   *
   * @return bool
   *   Returns TRUE when the referenced media is hidden.
   */
  public function isHiddenVideo(): bool {
    $media = $this->getReferencedMediaEntity();

    if (!$media || !method_exists($media, 'isHidden')) {
      return FALSE;
    }

    return $media->isHidden();
  }

  /**
   * Gets the referenced media entity.
   *
   * @return \Drupal\helfi_media_remote_video\Entity\RemoteVideo|null
   *   The referenced media entity or NULL.
   */
  public function getReferencedMediaEntity(): ?RemoteVideo {
    if (
      !$this->hasField('field_remote_video') ||
      $this->get('field_remote_video')->isEmpty()
    ) {
      return NULL;
    }
    $referencedEntities = $this->get('field_remote_video')->referencedEntities();
    if (empty($referencedEntities)) {
      return NULL;
    }
    $media = reset($referencedEntities);
    return $media instanceof RemoteVideo ? $media : NULL;
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
