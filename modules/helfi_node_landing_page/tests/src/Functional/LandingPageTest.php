<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_node_landing_page\Functional;

use Drupal\Tests\helfi_platform_config\Functional\BrowserTestBase;

/**
 * Tests helfi_node_landing_page module.
 *
 * @group helfi_platform_config
 */
class LandingPageTest extends BrowserTestBase {

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
   * Tests landing page content type.
   */
  public function testLandingPage() : void {
    $this->assertFrontPageLanguages();

    $paragraphTypes = [
      'helfi_paragraphs_hero' => [
        ['field_hero'],
        'hero',
      ],
      'helfi_paragraphs_text' => [
        ['field_content'],
        'phasing',
      ],
    ];

    foreach ($paragraphTypes as $module => $type) {
      [$fields, $paragraphType] = $type;
      $this->enableModule($module);
      $this->assertParagraphTypeEnabled('node', 'landing_page', $fields, $paragraphType);
    }
  }

}
