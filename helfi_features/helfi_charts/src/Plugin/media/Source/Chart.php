<?php

declare(strict_types = 1);

namespace Drupal\helfi_charts\Plugin\media\Source;

use Drupal\media\MediaSourceBase;
use Drupal\media\MediaTypeInterface;

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
  // https://app.powerbi.com/view?r=eyJrIjoiYjE5OTFhMmEtMWYzNC00YjY2LTllODMtMzhhZDRiNTJiMDQ5IiwidCI6IjNmZWI2YmMxLWQ3MjItNDcyNi05NjZjLTViNThiNjRkZjc1MiIsImMiOjh9
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

  /**
   * {@inheritdoc}
   */
  public function createSourceField(MediaTypeInterface $type) {
    $storage = $this->getSourceFieldStorage() ?: $this->createSourceFieldStorage();
    return $this->entityTypeManager
      ->getStorage('field_config')
      ->create([
        'field_storage' => $storage,
        'bundle' => $type->id(),
        'label' => 'Url',
        'required' => TRUE,
      ]);
  }

}
