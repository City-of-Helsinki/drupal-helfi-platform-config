<?php

declare(strict_types=1);

namespace Drupal\helfi_tpr_config\Entity;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Bundle class for service_list_search paragraph.
 */
class ServiceListSearch extends Paragraph implements ParagraphInterface {

  /**
   * Get services list for search.
   *
   * @return string|null
   *   Concatenated list of service list ids and target ids.
   */
  public function getServicesListSearch(): ?string {
    $ids = '';
    $service_ids = '';

    if ($this->hasField('field_service_list_services')) {
      $ids = $this->getListAsString(
        'field_service_list_services',
        'target_id'
      );
    }
    if ($this->hasField('field_service_list_service_ids')) {
      $service_ids = $this->getListAsString(
        'field_service_list_service_ids',
        'value'
      );
    }

    return "$ids|$service_ids";
  }

  /**
   * Turn list fields to comma separated strings, used by service lists.
   *
   * @param string $field_name
   *   Name of the field.
   * @param string $type
   *   Use 'target_id' for entity reference, 'value' for string or number.
   *
   * @return string
   *   Comma separated string of list items.
   */
  private function getListAsString(string $field_name, string $type): string {
    return implode(',', array_map(function ($service) use ($type) {
      return $service[$type];
    }, $this->get($field_name)->getValue()));
  }

}
