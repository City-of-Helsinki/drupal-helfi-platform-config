<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\EntityNotifications;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * A notification shown for a content entity.
 */
final readonly class EntityNotification {

  public function __construct(
    public TranslatableMarkup|string $label,
    public Url $url,
  ) {}

}
