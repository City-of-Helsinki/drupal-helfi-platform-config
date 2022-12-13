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
        'node',
        'page',
        ['field_hero'],
        'hero',
      ],
      'helfi_paragraphs_text' => [
        'node',
        'page',
        ['field_content', 'field_lower_content'],
        'text',
      ],
    ];

    foreach ($paragraphTypes as $module => $type) {
      [$entityType, $bundle, $fields, $paragraphType] = $type;
      // Paragraph type should be disabled by default.
      $this->assertParagraphTypeDisabled($entityType, $bundle, $fields, $paragraphType);
      // Enable the module and make sure the paragraph type is enabled.
      $this->enableModule($module);
      $this->assertParagraphTypeEnabled($entityType, $bundle, $fields, $paragraphType);
    }
  }

}
