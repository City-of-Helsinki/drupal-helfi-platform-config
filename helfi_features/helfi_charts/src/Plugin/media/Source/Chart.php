<?php

declare(strict_types = 1);

namespace Drupal\helfi_charts\Plugin\media\Source;

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
 *     "media_library_add" = "Drupal\helfi_charts\Form\HelfiChartAddForm"
 *   }
 * )
 */
final class Chart extends MediaSourceBase {

  /**
   * Valid Power BI URL.
   */
  public const CHART_POWERBI_URL = 'app.powerbi.com';

  /**
   * List of valid map base urls.
   */
  public const VALID_URLS = [
    'powerbi' => self::CHART_POWERBI_URL,
  ];

  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes() : array {
    return [];
  }

}
