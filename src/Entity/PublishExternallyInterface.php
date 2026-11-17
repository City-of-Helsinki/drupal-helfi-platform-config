<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Entity;

/**
 * An interface for nodes that can be published to all instances.
 *
 * Only Etusivu has the 'field_publish_externally' field. Other instances show
 * those nodes through the remote entities.
 */
interface PublishExternallyInterface {

  /**
   * Whether the entity is published to all instances.
   *
   * @return bool
   *   TRUE if the entity is published externally.
   */
  public function publishExternally(): bool;

  /**
   * Sets whether the entity is published to all instances.
   *
   * @param bool $status
   *   TRUE if the entity should be published externally.
   *
   * @return $this
   *   The entity.
   */
  public function setPublishExternally(bool $status): static;

  /**
   * Gets the cache tags to invalidate on other instances.
   *
   * @return string[]
   *   The cache tags.
   */
  public function getExternalCacheTags(): array;

}
