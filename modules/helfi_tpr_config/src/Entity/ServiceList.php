<?php

declare(strict_types=1);

namespace Drupal\helfi_tpr_config\Entity;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Bundle class for service_list paragraph.
 */
class ServiceList extends Paragraph implements ParagraphInterface {

  /**
   * Get list of services.
   *
   * @return string|null
   *   Comma separated string of service entity ids.
   */
  public function getServicesList(): ?string {
    if ($this->hasField('field_service_list_services')) {
      return $this->getListAsString(
        'field_service_list_services',
        'target_id'
      );
    }
    return NULL;
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

  /**
   * Get the id of the paragraph to search a parent for.
   *
   * @return string|int|null
   *   Id of the paragraph.
   */
  public function getSearchParentParagraph(): string|int|null {
    return $this->id();
  }

}
