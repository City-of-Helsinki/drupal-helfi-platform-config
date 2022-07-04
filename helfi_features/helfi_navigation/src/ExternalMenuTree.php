<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation;

/**
 * Class for building menu tree from external source.
 */
class ExternalMenuTree {

  /**
   * Constructs an instance of ExternalMenuTree.
   */
  public function __construct(protected array $tree) {}

  /**
   * Getter method for tree instance variable.
   *
   * @return array
   *   The tree.
   */
  public function getTree() : array {
    return $this->tree;
  }

}
