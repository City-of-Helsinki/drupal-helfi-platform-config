<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation;

/**
 * Defines an interface for creating external menu item.
 */
interface ExternalMenuBlockInterface {

  /**
   * Return the menu data in JSON form.
   *
   * @return string
   *   The resulting json string.
   */
  public function getData(): string;

  /**
   * Returns the maximum depth of the menu.
   *
   * @return int
   *   The maximum depth.
   */
//  public function maxDepth(): int;

}
