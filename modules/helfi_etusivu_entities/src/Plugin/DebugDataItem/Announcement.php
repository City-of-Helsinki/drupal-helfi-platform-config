<?php

declare(strict_types=1);

namespace Drupal\helfi_etusivu_entities\Plugin\DebugDataItem;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_api_base\Attribute\DebugDataItem;

/**
 * Debug data client.
 *
 * This is used to ensure the current instance has access to
 * API used by Announcements.
 */
#[DebugDataItem(
  id: 'etusivu_entities_announcement',
  title: new TranslatableMarkup('Etusivu entities: Announcement'),
)]
final class Announcement extends ApiAvailabilityBase {

  /**
   * {@inheritdoc}
   */
  protected function getBasePath(): string {
    return 'node/announcement';
  }

}
