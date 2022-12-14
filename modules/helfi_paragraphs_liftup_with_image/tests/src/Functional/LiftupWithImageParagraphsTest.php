<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_paragraphs_liftup_with_image\Functional;

use Drupal\Tests\helfi_platform_config\Functional\BrowserTestBase;

/**
 * Tests helfi_paragraphs_liftup_with_image module.
 *
 * @group helfi_platform_config
 */
class LiftupWithImageParagraphsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_paragraphs_liftup_with_image',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests liftup_with_image paragraph.
   */
  public function testParagraphs() : void {
    $this->assertFrontPageLanguages();
  }

}
