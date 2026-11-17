<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Entity;

/**
 * Publishes nodes to all instances through 'field_publish_externally'.
 *
 * @see \Drupal\helfi_platform_config\Entity\PublishExternallyInterface
 */
trait PublishExternallyTrait {

  /**
   * {@inheritdoc}
   */
  public function publishExternally(): bool {
    return $this->hasField('field_publish_externally')
      && (bool) $this->get('field_publish_externally')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setPublishExternally(bool $status): static {
    $this->set('field_publish_externally', $status);
    return $this;
  }

}
