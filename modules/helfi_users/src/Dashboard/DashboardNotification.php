<?php

declare(strict_types=1);

namespace Drupal\helfi_users\Dashboard;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * A notification shown for a content entity in the user dashboard.
 */
final readonly class DashboardNotification {

  public function __construct(
    public TranslatableMarkup|string $label,
    public Url $url,
  ) {}

}
