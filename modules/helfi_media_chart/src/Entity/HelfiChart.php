<?php

declare(strict_types=1);

namespace Drupal\helfi_media_chart\Entity;

use Drupal\helfi_media\Entity\MediaEntityBundle;
use Drupal\media\MediaInterface;

/**
 * Bundle class for helfi media chart media entity.
 */
class HelfiChart extends MediaEntityBundle implements MediaInterface {

  /**
   * Get service provider url.
   *
   * @return string
   *   Url of the service provider.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function getServiceUrl(): string {
    $chart_url = $this->get('field_helfi_chart_url')
      ?->first()
      ?->getString();
    $url_parts = parse_url($chart_url);
    return $url_parts['scheme'] . "://" . $url_parts['host'];
  }

  /**
   * Get the title of chart.
   *
   * @return string|null
   *   The title of the chart.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function getMediaTitle(): ?string {
    $title = (string) $this->get('field_helfi_chart_title')
      ?->first()
      ?->getString();

    return empty($title) ? NULL : $title;
  }

}
