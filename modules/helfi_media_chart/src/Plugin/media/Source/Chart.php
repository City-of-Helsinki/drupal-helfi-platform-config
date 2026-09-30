<?php

declare(strict_types=1);

namespace Drupal\helfi_media_chart\Plugin\media\Source;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_media_chart\Form\HelfiChartAddForm;
use Drupal\media\Attribute\MediaSource;
use Drupal\media\MediaSourceBase;

/**
 * Chart entity media source.
 */
#[MediaSource(
  id: 'helfi_chart',
  label: new TranslatableMarkup('Chart'),
  description: new TranslatableMarkup('Provides business logic and metadata for charts from services like Power BI.'),
  allowed_field_types: ['link'],
  forms: [
    'media_library_add' => HelfiChartAddForm::class,
  ],
)]
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
