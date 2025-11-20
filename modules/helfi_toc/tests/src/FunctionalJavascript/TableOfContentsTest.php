<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_toc\FunctionalJavascript;

use Drupal\filter\Entity\FilterFormat;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests table of contents.
 *
 * @group helfi_toc
 */
class TableOfContentsTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'filter',
    'helfi_platform_config',
    'helfi_toc',
    'node',
    'text',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create page content type.
    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Page',
    ]);

    // Enable the helfi_toc module.
    \Drupal::service('module_installer')->install(['helfi_toc']);

    // Get page full display.
    $display = \Drupal::service('entity_display.repository')->getViewDisplay('node', 'page', 'full');

    // Add body field to page full display if it doesn't exist.
    if (!$display->getComponent('body')) {
      $display->setComponent('body', [
        'type' => 'text_default',
        'label' => 'hidden',
      ]);
    }

    // Add toc_enabled field to page full display if it doesn't exist.
    if (!$display->getComponent('toc_enabled')) {
      $display->setComponent('toc_enabled', [
        'type' => 'boolean',
        'label' => 'hidden',
        'weight' => 0,
        'settings' => [
          'format' => 'custom',
          'format_custom_false' => '',
          'format_custom_true' => '1',
        ],
      ]);
    }
    $display->save();

    // Invalidate caches.
    \Drupal::service('cache_tags.invalidator')->invalidateTags(['rendered']);
    \Drupal::service('theme.registry')->reset();

    // Create filtered_html format.
    $filtered_html_format = FilterFormat::create([
      'format' => 'filtered_html',
      'name' => 'Filtered HTML',
      'weight' => 0,
      'filters' => [],
    ]);
    $filtered_html_format->save();
  }

  /**
   * Tests table of contents javascript functionalities.
   */
  public function testTableOfContents(): void {
    $this->testTocEnabled();
    $this->testTocDisabled();
  }

  /**
   * Tests the table of contents when it is enabled.
   */
  protected function testTocEnabled(): void {
    $assert_session = $this->assertSession();

    // Generate 10 H2.
    for ($i = 0; $i <= 10; $i++) {
      $h2[] = "Section $i";
    }

    $headings = [
      'h2' => $h2,
      'h3' => ['Subsection A', 'Subsection B', 'Subsection C'],
      'h4' => ['Detail 1', 'Detail 2', 'Detail 3'],
    ];
    $content = $this->generateContent($headings);

    // Create a node with table of contents enabled.
    $node = $this->drupalCreateNode([
      'title' => 'Test Page with table of contents',
      'type' => 'page',
      'status' => 1,
      'toc_enabled' => 1,
      'body' => [
        'value' => $content,
        'format' => 'filtered_html',
      ],
    ]);
    $node->save();

    // Load the node page with the 'full' view mode.
    $this->drupalGet($node->toUrl('canonical', ['absolute' => TRUE]));

    // Wait for the table of contents to be visible and test the
    // table of contents visibility.
    $assert_session->pageTextContains('On this page');
    $assert_session->pageTextNotContains('Loading table of contents');
    $assert_session->elementNotExists('css', '.js-remove');

    // Check that the TOC contains links to all h2 headings.
    foreach ($h2 as $heading) {
      $assert_session->elementExists('css', sprintf('.table-of-contents a:contains("%s")', $heading));
    }
  }

  /**
   * Tests the table of contents when it is disabled.
   */
  protected function testTocDisabled(): void {
    $node = $this->drupalCreateNode([
      'title' => 'Test Page without TOC',
      'type' => 'page',
      'status' => 1,
      'toc_enabled' => 0,
      'body' => [
        'value' => '<h2>Test Section</h2><p>Test content</p>',
        'format' => 'full_html',
      ],
    ]);
    $node->save();
    $this->drupalGet($node->toUrl());
    $this->assertSession()->elementNotExists('css', '.table-of-contents');
    $this->assertSession()->pageTextNotContains('Loading table of contents');
    $this->assertSession()->pageTextNotContains('On this page');
  }

  /**
   * Helper function to generate content with headings.
   *
   * @param array $headings
   *   An array of headings.
   *
   * @return string
   *   Returns the generated content as a string.
   */
  protected function generateContent(array $headings): string {
    $content = '<main class="layout-main-wrapper">';

    foreach ($headings['h2'] as $h2) {
      $content .= "<h2>$h2</h2>";
      $content .= "<p>Content for $h2. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>";

      foreach (array_slice($headings['h3'], 0, 2) as $h3) {
        $content .= "<h3>$h3</h3>";
        $content .= "<p>Content for $h3. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>";

        if (!empty($headings['h4'])) {
          $h4 = reset($headings['h4']);
          $content .= "<h4>$h4</h4>";
          $content .= "<p>Content for $h4. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.</p>";
        }
      }
    }
    $content .= '</main>';

    return $content;
  }

}
