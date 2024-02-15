<?php

declare(strict_types=1);

namespace Drupal\helfi_media_chart\Plugin\media\Source;

use Drupal\media\MediaSourceBase;

/**
 * Chart entity media source.
 *
 * @MediaSource(
 *   id = "helfi_chart",
 *   label = @Translation("Chart"),
 *   description = @Translation("Provides business logic and metadata for charts from services like Power BI."),
 *   allowed_field_types = {"link"},
 *   forms = {
 *     "media_library_add" = "Drupal\helfi_media_chart\Form\HelfiChartAddForm"
 *   }
 * )
 */
final class Chart extends MediaSourceBase {

  /**
   * Valid Power BI URL.
   */
  public const CHART_POWERBI_URL = [
    'app.powerbi.com',
    'playground.powerbi.com',
  ];

  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes() : array {
    return [];
  }

}
