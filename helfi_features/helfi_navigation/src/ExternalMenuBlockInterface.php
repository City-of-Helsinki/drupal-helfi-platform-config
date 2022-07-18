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
  public function getMaxDepth(): int;

  /**
   * Returns the starting level of the menu.
   *
   * @return int
   *   The starting level.
   */
  public function getStartingLevel(): int;

  /**
   * Returns the information of should the menu be rendered as fallback menu.
   *
   * @return bool
   *   Should the menu follow active trail and be rendered as fallback menu.
   */
  public function getFallback(): bool;

  /**
   * Returns the information of should the items be expanded by default.
   *
   * @return bool
   *   Should the items be expanded.
   */
  public function getExpandAllItems(): bool;

}
