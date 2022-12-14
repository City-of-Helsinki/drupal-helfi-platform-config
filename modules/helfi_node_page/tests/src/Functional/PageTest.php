<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_node_page\Functional;

use Drupal\Tests\helfi_platform_config\Functional\BrowserTestBase;

/**
 * Tests helfi_node_page module.
 *
 * @group helfi_platform_config
 */
class PageTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_base_content',
    'helfi_node_page',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests page content type.
   */
  public function testDefault() : void {
    $this->assertFrontPageLanguages();

    $paragraphTypes = [
      'helfi_paragraphs_hero' => [
        ['field_hero'],
        'hero',
      ],
      'helfi_paragraphs_text' => [
        ['field_content', 'field_lower_content'],
        'text',
      ],
    ];

    foreach ($paragraphTypes as $module => $type) {
      [$fields, $paragraphType] = $type;
      $this->enableModule($module);
      $this->assertParagraphTypeEnabled('node', 'page', $fields, $paragraphType);
    }
  }

}
