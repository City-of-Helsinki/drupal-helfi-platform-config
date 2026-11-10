<?php

declare(strict_types=1);

namespace Drupal\helfi_node_announcement\Entity;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\helfi_platform_config\Entity\PublishExternallyInterface;
use Drupal\helfi_platform_config\Entity\PublishExternallyTrait;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Bundle class for Announcement node.
 */
class Announcement extends Node implements NodeInterface, PublishExternallyInterface {

  use PublishExternallyTrait;
  use StringTranslationTrait;

  /**
   * The cache tag of remote announcements on other instances.
   */
  public const EXTERNAL_CACHE_TAG = 'helfi_external_entity_announcement';

  /**
   * {@inheritdoc}
   */
  public function getExternalCacheTags(): array {
    return [self::EXTERNAL_CACHE_TAG];
  }

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

  /**
   * Populates the announcement from a remote announcement.
   *
   * Remote announcements are rendered as unsaved announcement nodes.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $remote
   *   The remote announcement.
   *
   * @return $this
   *   The announcement.
   */
  public function populateFromRemoteEntity(FieldableEntityInterface $remote): static {
    $linkUrl = NULL;
    $linkText = NULL;
    if ($remote->hasField('announcement_link_text')) {
      $linkText = $remote->get('announcement_link_text')->value;
      $linkUrl = $remote->get('announcement_link_url')->value;
    }

    $this->set('uuid', $remote->get('uuid')->value);
    $this->set('langcode', $remote->get('langcode')->value);
    $this->set('title', $remote->get('title')->value);
    // Run the body through the 'minimal' text format.
    $this->set('body', ['value' => $remote->get('body')->value, 'format' => 'minimal']);
    $this->set('status', $remote->get('status')->value);
    $this->set('field_announcement_title', $remote->get('announcement_assistive_technology_close_button_title')->value);
    $this->set('field_announcement_type', $remote->get('announcement_type')->value);
    $this->set('field_announcement_link', ['uri' => $linkUrl, 'title' => $linkText]);

    return $this;
  }

}
