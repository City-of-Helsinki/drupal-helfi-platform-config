<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\FunctionalJavascript;

use Drupal\filter\Entity\FilterFormat;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests heading anchor buttons.
 *
 * @group helfi_platform_config
 */
class HeadingAnchorButtonsTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'filter',
    'helfi_platform_config',
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

    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Page',
    ]);

    FilterFormat::create([
      'format' => 'unfiltered',
      'name' => 'Unfiltered',
      'filters' => [],
    ])->save();

    // Anchor buttons are attached only for editors.
    $this->drupalLogin($this->drupalCreateUser(['administer nodes']));
  }

  /**
   * Tests heading text with inline elements.
   */
  public function testHeadingWithInlineElement(): void {
    $node = $this->drupalCreateNode([
      'type' => 'page',
      'body' => [
        'value' => '<main class="layout-main-wrapper"><h2 id="sauna">I went to the <span lang="fi">sauna</span> to relax</h2></main>',
        'format' => 'unfiltered',
      ],
    ]);
    $this->drupalGet($node->toUrl());

    $assert_session = $this->assertSession();
    $assert_session->waitForElement('css', 'h2#sauna .heading-anchor-button');

    $heading_text = $assert_session->elementExists('css', 'h2#sauna > .heading-text');
    $this->assertSame('I went to the sauna to relax', $heading_text->getText(), 'Heading text keeps the inline element in place.');
    $assert_session->elementExists('css', 'h2#sauna > .heading-text > span[lang="fi"]');
  }

}
