<?php

declare(strict_types=1);

namespace Drupal\helfi_etusivu_entities\Plugin\DebugDataItem;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_api_base\Attribute\DebugDataItem;

/**
 * Debug data client.
 *
 * This is used to ensure the current instance has access to
 * API used by Surveys.
 */
#[DebugDataItem(
  id: 'etusivu_entities_survey',
  title: new TranslatableMarkup('Etusivu entities: Survey'),
)]
final class Survey extends ApiAvailabilityBase {

  /**
   * {@inheritdoc}
   */
  protected function getBasePath(): string {
    return 'node/survey';
  }

}
