<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_remote_video\Hook;

use Drupal\Core\Cache\Cache;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\helfi_paragraphs_remote_video\Entity\ParagraphRemoteVideo;

/**
 * Hooks for helfi_paragraphs_remote_video module.
 */
class ParagraphRemoteVideoHooks {

  use AutowireTrait;

  /**
   * Implements hook_preprocess_HOOK().
   */
  #[Hook('preprocess_paragraph__remote_video')]
  public static function preprocessParagraphRemoteVideo(array &$variables): void {
    $paragraph = $variables['paragraph'] ?? NULL;

    if (
      !$paragraph instanceof ParagraphRemoteVideo ||
      $paragraph->get('field_remote_video')->isEmpty()
    ) {
      return;
    }

    // Set the iframe title.
    $paragraph->setMediaEntityIframeTitle();

    // Set is_hidden_video variable to template if the remote video is hidden.
    $variables['is_hidden_video'] = $paragraph->isHiddenVideo();

    // Add cache tags to referenced media field.
    $variables['content']['field_remote_video'][0]['#cache']['tags'] = Cache::mergeTags(
      $variables['content']['field_remote_video'][0]['#cache']['tags'] ?? [],
      $paragraph->getCacheTags()
    );
  }

}
