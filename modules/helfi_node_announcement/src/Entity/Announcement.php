<?php

declare(strict_types=1);

namespace Drupal\helfi_node_announcement\Entity;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Bundle class for Announcement node.
 */
class Announcement extends Node implements NodeInterface {

  use StringTranslationTrait;

  /**
   * Get announcement type.
   *
   * @return string
   *   The announcement type.
   */
  public function getAnnouncementType(): string {
    return $this->get('field_announcement_type')->getString();
  }

  /**
   * Get announcement labels.
   *
   * @return array
   *   The announcement labels.
   */
  public function getLabels(): array {
    $labels['type'] = match($this->get('field_announcement_type')->getString()) {
      'alert' => $this->t('Alert', options: ['context' => 'helfi_node_announcement']),
      'attention' => $this->t('Attention', options: ['context' => 'helfi_node_announcement']),
      default => $this->t('Notification', options: ['context' => 'helfi_node_announcement']),
    };

    $labels['close'] = $this->t('Close', options: ['context' => 'helfi_node_announcement']);
    return $labels;
  }

}
