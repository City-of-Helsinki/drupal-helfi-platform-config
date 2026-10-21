<?php

declare(strict_types=1);

namespace Drupal\hdbt_admin_tools\Hook;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Koro hook implementations for HDBT Admin tools.
 */
class KoroHooks {

  /**
   * Implements hook_page_attachments().
   *
   * @param array<string, mixed> $attachments
   *   The page attachments.
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    // The footer koro depends on the koro of the current path.
    $attachments['#cache']['contexts'][] = 'koro';
  }

  /**
   * Implements hook_ENTITY_TYPE_view_alter() for paragraphs.
   *
   * @param array<string, mixed> $build
   *   The render array.
   * @param \Drupal\Core\Entity\EntityInterface $paragraph
   *   The paragraph.
   * @param \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display
   *   The view display.
   */
  #[Hook('paragraph_view_alter')]
  public function paragraphViewAlter(array &$build, EntityInterface $paragraph, EntityViewDisplayInterface $display): void {
    // Hero and content cards render a koro of the current path.
    $build['#cache']['contexts'][] = 'koro';
  }

}
