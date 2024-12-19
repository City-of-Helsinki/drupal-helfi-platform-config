<?php

declare(strict_types=1);

namespace Drupal\hdbt_cookie_banner_test\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Provides a test page for the custom module.
 */
class TestController extends ControllerBase {

  /**
   * Test page content.
   *
   * @return array
   *   Render array.
   */
  public function content() {
    return [
      '#type' => 'inline_template',
      '#template' => '<p>Test Content</p><div class="test-footer"></div>',
    ];
  }

}
