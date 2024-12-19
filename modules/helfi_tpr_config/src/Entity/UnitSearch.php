<?php

declare(strict_types=1);

namespace Drupal\helfi_tpr_config\Entity;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Bundle class for unit_search paragraph.
 */
class UnitSearch extends Paragraph implements ParagraphInterface {

  /**
   * Get unit lists.
   *
   * @return string|null
   *   Comma separated list of unit ids.
   */
  public function getUnitsList(): ?string {
    if ($this->hasField('field_unit_search_units')) {
      return $this->getListAsString(
        'field_unit_search_units',
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
