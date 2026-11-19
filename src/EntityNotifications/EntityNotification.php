<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\EntityNotifications;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * A notification shown for a content entity.
 */
final readonly class EntityNotification {

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string $label
   *   The link text.
   * @param \Drupal\Core\Url $url
   *   The link URL. Classes for the link can be set in the 'attributes'
   *   option.
   * @param string|null $icon
   *   The hdbt icon shown before the link, or NULL to show none, for example
   *   when a link class provides its own icon.
   */
  public function __construct(
    public TranslatableMarkup|string $label,
    public Url $url,
    public ?string $icon = 'info-circle-fill',
  ) {}

}
