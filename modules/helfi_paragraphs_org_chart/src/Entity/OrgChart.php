<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_org_chart\Entity;

use Drupal\paragraphs\Entity\Paragraph;

/**
 * Bundle class for org chart paragraph.
 */
class OrgChart extends Paragraph {

  /**
   * Gets the paragraph title.
   *
   * @return string
   *   The title.
   */
  public function getTitle() : string {
    return $this->get('field_org_chart_title')->value;
  }

  /**
   * Gets the paragraph description.
   *
   * @return string
   *   The description.
   */
  public function getDescription() : string {
    return $this->get('field_org_chart_desc')->value;
  }

  /**
   * Get starting organization.
   *
   * @return string
   *   Starting organization.
   */
  public function getStartingOrganization(): string {
    return $this->get('field_org_chart_start')->value;
  }

  /**
   * Get org chart depth.
   *
   * @return int
   *   Chart depth.
   */
  public function getDepth(): int {
    return (int) $this->get('field_org_chart_depth')->value;
  }

}
