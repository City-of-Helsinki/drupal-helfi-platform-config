<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_node_landing_page\Functional;

use Drupal\Tests\helfi_platform_config\Functional\ParagraphTestBase;

/**
 * Tests helfi_node_landing_page module.
 *
 * @group helfi_platform_config
 */
class LandingPageTest extends ParagraphTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_base_content',
    'helfi_node_landing_page',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function getParagraphTypes(): array {
    return helfi_node_landing_page_helfi_paragraph_types();
  }

  /**
   * Tests landing page content type.
   */
  public function testLandingPage() : void {
    $this->assertFrontPageLanguages();

    $this->assertParagraphTypeEnabled();
  }

}
