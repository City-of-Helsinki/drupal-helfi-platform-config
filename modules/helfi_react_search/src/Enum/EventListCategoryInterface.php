<?php

declare(strict_types=1);

namespace Drupal\helfi_react_search\Enum;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Interface for event list category enums.
 */
interface EventListCategoryInterface extends \BackedEnum {

  /**
   * Get linked event keywords for this category.
   *
   * @return string[]
   *   Keywords that can be used in linked events API.
   */
  public function keywords(): array;

  /**
   * Get category translation.
   */
  public function translation(): TranslatableMarkup;

}
