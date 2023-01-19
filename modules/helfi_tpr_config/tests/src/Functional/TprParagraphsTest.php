<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_tpr_config\Functional;

use Drupal\Tests\helfi_platform_config\Functional\BrowserTestBase;

/**
 * Tests helfi_tpr_config module.
 *
 * @group helfi_platform_config
 */
class TprParagraphsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_tpr_config',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests text paragraph.
   */
  public function testParagraphs() : void {
    $this->assertFrontPageLanguages();
  }

}
