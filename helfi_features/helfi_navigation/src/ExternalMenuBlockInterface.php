<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation;

/**
 * Defines an interface for creating external menu item.
 */
interface ExternalMenuBlockInterface {

  /**
   * Returns the maximum depth of the menu.
   *
   * @return int
   *   The maximum depth.
   */
  public function getMaxDepth(): int;

  /**
   * Returns the starting level of the menu.
   *
   * @return int
   *   The starting level.
   */
  public function getStartingLevel(): int;

  /**
   * Returns the information of should the items be expanded by default.
   *
   * @return bool
   *   Should the items be expanded.
   */
  public function getExpandAllItems(): bool;

}
