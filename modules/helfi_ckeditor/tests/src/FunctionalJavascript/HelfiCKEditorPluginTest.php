<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ckeditor\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\Core\Url;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\ckeditor5\Traits\CKEditor5TestTrait;
use Drupal\user\Entity\User;

/**
 * Tests CKEditor 5 Helfi plugins.
 *
 * @group helfi_ckeditor
 */
class HelfiCKEditorPluginTest extends WebDriverTestBase {

  use CKEditor5TestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'ckeditor5',
    'filter',
    'helfi_ckeditor',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The admin user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected User $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Page',
    ]);

    $this->user = $this->drupalCreateUser([
      'use text format full_html',
      'edit any page content',
      'administer site configuration',
    ]);
    $this->drupalLogin($this->user);
  }

  /**
   * Tests CKEditor 5 custom plugins.
   */
  public function testHelfiPlugins(): void {
    $this->assertLinkPlugin();
    $this->assertLegacyButtonLinks();
    $this->assertLanguageSelection();
    $this->assertLanguageUnSelection();
    $this->assertLanguageCodeCleanup();
    $this->assertAddingQuote();
    $this->assertAddingTable();
    $this->assertNonBreakingSpaces();
    $this->assertLinkSpaces();
  }

  /**
   * Tests CKEditor 5 Helfi link plugin.
   */
  protected function assertLinkPlugin(): void {
    $test_url_protocol = 'https://';
    $test_url_address = 'www.test.hel.ninja/fi';
    $test_url = $test_url_protocol . $test_url_address;

    // Initialize the CKEditor with a suitable markup.
    $edit_url = $this->initializeEditor('');

    /** @var \Drupal\FunctionalJavascriptTests\WebDriverWebAssert $assert_session */
    $assert_session = $this->assertSession();

    // Open the link dialog.
    $this->pressEditorButton('Link');

    // Check that the CKEditor 5 balloon (dialog) is visible.
    $balloon = $this->assertVisibleBalloon('.ck-link-form');

    // Check that the protocol field is visible and click it.
    $protocol_field = $balloon->find('css', '#protocol-ts-control');
    $this->assertTrue($protocol_field->isVisible(), 'Protocol field is not visible.');
    $protocol_field->click();

    // Choose https protocol.
    $assert_session->waitForElementVisible('css', 'div.ts-wrapper.ck-helfi-link-select-list.protocol');
    $protocol_select_list = $balloon->find('css', 'div.ts-wrapper.ck-helfi-link-select-list.protocol');
    $https_selection = $protocol_select_list->find('css', 'span[data-value="https"]');
    $this->assertTrue($https_selection->isVisible(), 'HTTPS selection is not visible.');
    $https_selection->click();

    // Check that the protocol field is not visible after adding href.
    $this->assertFalse($protocol_field->isVisible(), 'Protocol field is not visible.');

    // Find the href field.
    $link_field = $balloon->find('css', '.helfi-link-url-input .ck-input');
    // Check that there is a value in the href field,
    // set by the protocol selection.
    $this->assertNotEmpty($link_field);

    // Override the href field value with the test URL.
    // Test sanitation with Outlook safe links and trailing spaces.
    $dirty_url = "https://prefix.safelinks.protection.outlook.com/?url=https%3A//{$test_url_address}/&data=05%7C02%7Csome.email%40hel.test.ninja%7Ce8a754aca1414b62752%7C1%7C0%7C6%7CUnknown%7CTWFpbGZsn0%3D%7C0%7C%7C%7C&sdata=wk3kH%3D&reserved=0   ";
    $link_field->setValue($dirty_url);

    // Check that the protocol field is not visible after adding href.
    $this->assertFalse($protocol_field->isVisible(), 'Protocol field is not visible.');

    // Open the details summary.
    $details = $assert_session->waitForElementVisible('css', 'details.ck-helfi-link-details');
    $details->find('css', '.ck-helfi-link-details__summary')->click();

    // Select the "open the link in new window" option. Check that the initial
    // value is not checked, and check it.
    $link_new_window = $details->find('css', '.helfi-link--link-new-window');
    $this->assertTrue($link_new_window->isVisible(), 'Link new window is visible.');
    $input_new_window = $assert_session->waitForElementVisible('css', 'input#link-new-window');
    $this->assertFalse($input_new_window->isChecked());
    $input_new_window->check();

    // Do the same for the window confirmed option.
    $link_new_window_confirmed = $details->find('css', '.helfi-link--link-new-window-confirm');
    $this->assertTrue($link_new_window_confirmed->isVisible(), 'Link new window confirmed is visible.');
    $input_new_window_confirm = $assert_session->waitForElementVisible('css', 'input#link-new-window-confirm');
    $this->assertFalse($input_new_window_confirm->isChecked());
    $input_new_window_confirm->check();

    // Open the select list of styles and click it to make the options visible.
    $assert_session->waitForElementVisible('css', 'div.ts-wrapper.ck-helfi-link-select-list.variant');
    $button_selection_select_list = $details->find('css', 'div.ts-wrapper.ck-helfi-link-select-list.variant');
    $ts_control = $button_selection_select_list->find('css', '#variant-ts-control');
    $this->assertTrue($ts_control->isVisible(), 'Controls are visible.');
    $ts_control->click();

    // Select the primary button style.
    $primary_button = $assert_session->waitForElementVisible('css', 'span[data-value="primary"]');
    $this->assertNotNull($primary_button, 'Primary button should be visible');
    $primary_button->click();

    // Save the link.
    $balloon->pressButton('Insert');

    // Assert the balloon was closed by pressing its "Insert" button.
    $this->assertTrue($assert_session->waitForElementRemoved('css', '.ck-button-save'));

    // Check for Link plugin existence.
    $this->assertEditorButtonEnabled('Link');

    // Make sure all link attributes are populated.
    $linkit_link = $assert_session->waitForElementVisible('css', '.ck-content a');
    $this->assertNotNull($linkit_link);
    $this->assertSame("$test_url/", $linkit_link->getAttribute('href'));
    $this->assertSame('button', $linkit_link->getAttribute('data-hds-component'));
    $this->assertSame('_blank', $linkit_link->getAttribute('target'));
    $this->assertSame("$test_url/", $linkit_link->getText());

    // Save the node.
    $page = $this->getSession()->getPage();
    $page->pressButton('Save');

    // Check that the link is visible in the node.
    $link = $assert_session->waitForElementVisible('css', 'article p > a');
    $this->assertNotNull($link);
    $this->assertSame("$test_url/", $link->getAttribute('href'));
    $this->assertSame('button', $link->getAttribute('data-hds-component'));
    $this->assertSame('_blank', $link->getAttribute('target'));
    $this->assertSame("$test_url/", $link->getText());

    // Edit the node again.
    $this->drupalGet($edit_url);
    $this->waitForEditor();

    // Focus the editable area first.
    $content_area = $assert_session->waitForElementVisible('css', '.ck-editor__editable');
    $content_area->click();

    // Click on the link and then click on "Edit link".
    $assert_session->waitForElementVisible('css', '.ck-content a');
    $content_area->find('css', 'a')->click();
    $edit_link = $assert_session->waitForElementVisible('xpath', "//button[span[text()='Edit link']]");
    $edit_link->click();
    $assert_session->waitForElementVisible('css', '.ck-body-wrapper .ck-link-toolbar');

    // Check that the CKEditor 5 balloon (dialog) is visible.
    $balloon = $this->assertVisibleBalloon('.ck-link-form');

    // Check that the link has correct values set.
    $link_text = $balloon->find('css', '.helfi-link-text-input input');
    $link_url = $balloon->find('css', '.helfi-link-url-input input');

    // Check that the link has correct values set.
    $this->assertSame("$test_url/", $link_text->getValue());
    $this->assertSame("$test_url/", $link_url->getValue());
    $this->assertTrue($balloon->find('css', '#link-new-window')->isChecked());
    $this->assertTrue($balloon->find('css', '#link-new-window-confirm')->isChecked());
    $this->assertSame($balloon->find('css', '.ts-wrapper.ck-helfi-link-select-list span.item')->getAttribute('data-value'), 'primary');

    // Change the link values to default values and save the link.
    $link_url->setValue('#test');
    $link_text->setValue('#test');
    $this->getSession()->executeScript('document.getElementById("link-new-window").click();');
    $this->getSession()->executeScript('document.querySelector("span[data-value=link]").click();');
    $this->getSession()->executeScript('document.querySelector("button.helfi-link-save-button").click();');

    // Check that the modified link has correct attributes.
    $linkit_link = $assert_session->waitForElementVisible('css', '.ck-content a');
    $this->assertNotNull($linkit_link);
    $this->assertSame('#test', $linkit_link->getAttribute('href'));
    $this->assertNotSame('button', $linkit_link->getAttribute('data-hds-component'));
    $this->assertNotSame('_blank', $linkit_link->getAttribute('target'));

    // Test the anchor duplication bug.
    $initial_content = '<p>Some text <a href="https://some-domain.com?something=true">with a link</a>. Another sentence.</p>';
    $handled_content = '<p>Some text <a href="https://some-domain.com?something=true" data-is-external="true">with a link</a>. Another sentence.</p>';
    $edit_url = $this->initializeEditor($initial_content);

    /** @var \Drupal\FunctionalJavascriptTests\WebDriverWebAssert $assert_session */
    $assert_session = $this->assertSession();
    $this->drupalGet($edit_url);
    $this->waitForEditor();

    // Focus the editable area first.
    $content_area = $assert_session->waitForElementVisible('css', '.ck-editor__editable');
    $content_area->click();

    // Click on the link and then click on "Edit link".
    $assert_session->waitForElementVisible('css', '.ck-content a');
    $content_area->find('css', 'a')->click();
    $edit_link = $assert_session->waitForElementVisible('xpath', "//button[span[text()='Edit link']]");
    $edit_link->click();
    $assert_session->waitForElementVisible('css', '.ck-body-wrapper .ck-link-toolbar');

    // Check that the CKEditor 5 balloon (dialog) is visible.
    $balloon = $this->assertVisibleBalloon('.ck-link-form');

    // Save the link.
    $balloon->pressButton('Update');

    // Make sure the anchor tag duplication doesn't happen.
    $this->assertSame($handled_content, $this->getEditorDataAsHtmlString());
  }

  /**
   * Tests CKEditor 4 button links are converted to HDS button links.
   */
  protected function assertLegacyButtonLinks(): void {
    $cases = [
      'primary' => [
        'content' => '<p><a class="hds-button hds-button--primary" data-design="hds-button hds-button--primary" data-link-text="Test" data-protocol="https" href="#test">Test</a></p>',
        'variant' => NULL,
        'icon' => NULL,
      ],
      'secondary with icon' => [
        'content' => '<p><a class="hds-button hds-button--secondary" data-design="hds-button hds-button--secondary" data-link-text="Test" data-protocol="https" data-selected-icon="download" href="#test"><span class="hel-icon hel-icon--download" role="img" aria-hidden="true"></span><span class="hds-button__label">Test</span></a></p>',
        'variant' => 'secondary',
        'icon' => 'download',
      ],
    ];

    foreach ($cases as $name => $case) {
      $this->initializeEditor($case['content']);

      $link = $this->assertSession()->waitForElementVisible('css', '.ck-content a');
      $this->assertNotNull($link, "The editor loads a $name button link.");
      $this->assertSame('#test', $link->getAttribute('href'), "The $name button link keeps its href.");
      $this->assertSame('Test', $link->getText(), "The $name button link keeps its text.");
      $this->assertSame('button', $link->getAttribute('data-hds-component'), "The $name button link is an HDS button.");
      $this->assertSame($case['variant'], $link->getAttribute('data-hds-variant'), "The $name button link has the correct variant.");
      $this->assertSame($case['icon'], $link->getAttribute('data-hds-icon-start'), "The $name button link has the correct icon.");
      $this->assertNull($link->getAttribute('data-design'), "The $name button link has no data-design attribute.");
      $this->assertNull($link->getAttribute('data-protocol'), "The $name button link has no data-protocol attribute.");
      $this->assertNull($link->find('css', 'span'), "The $name button link has no spans.");
    }
  }

  /**
   * Test selecting a language from the Helfi language selector plugin.
   */
  protected function assertLanguageSelection(): void {
    // Initialize the CKEditor with a suitable markup.
    $this->initializeEditor('<p>Test</p><p>Testi</p><p>امتحان</p>');

    /** @var \Drupal\FunctionalJavascriptTests\WebDriverWebAssert $assert_session */
    $assert_session = $this->assertSession();

    // Go through the test content and select a language for each paragraph.
    foreach (['en' => 1, 'fi' => 2, 'ar' => 3] as $langcode => $index) {
      $dir = $langcode !== 'ar' ? 'ltr' : 'rtl';

      // Select the example text and choose a language for it.
      $this->selectTextInsideElement('.ck-content p:nth-of-type(' . $index . ')');
      $this->selectLanguage($langcode);

      // Make sure all attributes are populated correctly.
      $translated_paragraph = $assert_session->waitForElementVisible('css', '.ck-editor__main>.ck-content p:nth-of-type(' . $index . ') > span');
      $this->assertNotNull($translated_paragraph);
      $this->assertSame($dir, $translated_paragraph->getAttribute('dir'));
      $this->assertSame($langcode, $translated_paragraph->getAttribute('lang'));
    }
  }

  /**
   * Test unselecting a language from the Helfi language selector plugin.
   */
  protected function assertLanguageUnSelection(): void {
    $test_content = '<p><span lang="en" dir="ltr">Test</span></p><p><span lang="fi" dir="ltr">Testi</span></p><p><span lang="ar" dir="rtl">امتحان</span></p>';

    // Initialize the CKEditor with a suitable markup.
    $this->initializeEditor($test_content);

    /** @var \Drupal\FunctionalJavascriptTests\WebDriverWebAssert $assert_session */
    $assert_session = $this->assertSession();

    // Go through the test content and select a language for each paragraph.
    foreach (['en' => 1, 'fi' => 2, 'ar' => 3] as $index) {
      // Select the example text and choose a language for it.
      $this->selectTextInsideElement('.ck-content p:nth-of-type(' . $index . ')');

      // Skip English as it is the original language and therefore there is no
      // lang-attribute. CKEditor removes the default language attribute.
      if ($index === 1) {
        continue;
      }

      $this->unSelectLanguage();
      $non_translated_paragraph = $assert_session->waitForElementVisible('css', '.ck-editor__main>.ck-content p:nth-of-type(' . $index . ')');
      $this->assertNotNull($non_translated_paragraph);
      $this->assertNull($non_translated_paragraph->find('css', 'span'));
    }
  }

  /**
   * Test the language codes are cleaned up and the bare spans are removed.
   */
  protected function assertLanguageCodeCleanup(): void {
    foreach (['en', 'EN', 'en-GB', 'EN-gb', 'en-US'] as $langcode) {
      $this->initializeEditor('<p>Text <span lang="' . $langcode . '" dir="ltr">in English</span></p>');
      $this->assertSame('<p>Text in English</p>', $this->getEditorDataAsHtmlString(), "The current language $langcode is removed.");
    }

    foreach (['X-NONE', 'qq', 'qq-QQ', 'not a code'] as $langcode) {
      $this->initializeEditor('<p>Text <span lang="' . $langcode . '" dir="ltr">unknown</span></p>');
      $this->assertSame('<p>Text unknown</p>', $this->getEditorDataAsHtmlString(), "The unknown language $langcode is removed.");
    }

    $languages = [
      'FI' => '<span lang="fi" dir="ltr">',
      'fi-FI' => '<span lang="fi" dir="ltr">',
      'SV-fi' => '<span lang="sv" dir="ltr">',
      'ar-SA' => '<span lang="ar" dir="rtl">',
      'zh-Hant' => '<span lang="zh-hant" dir="ltr">',
      'zh-CN' => '<span lang="zh" dir="ltr">',
      'fil' => '<span lang="fil" dir="ltr">',
    ];

    foreach ($languages as $langcode => $expected) {
      $this->initializeEditor('<p>Text <span lang="' . $langcode . '">other</span></p>');
      $this->assertSame("<p>Text {$expected}other</span></p>", $this->getEditorDataAsHtmlString(), "The language $langcode is converted to the Drupal language code.");
    }

    // The text direction comes from the language.
    $this->initializeEditor('<p>Text <span lang="fi" dir="rtl">sauna</span></p>');
    $this->assertSame('<p>Text <span lang="fi" dir="ltr">sauna</span></p>', $this->getEditorDataAsHtmlString());

    // Spans without attributes are unwrapped.
    $this->initializeEditor('<p><span>Text before the link </span><a href="https://www.hel.fi"><span>Link text</span></a><span>.</span></p>');
    $this->assertMatchesRegularExpression('#<p>Text before the link <a [^>]+>Link text</a>\.</p>#', $this->getEditorDataAsHtmlString());

    // Marked non-breaking spaces are kept.
    $nbsp_content = '<p>Test<span data-nbsp="">&nbsp;</span>content</p>';
    $this->initializeEditor($nbsp_content);
    $this->assertSame($nbsp_content, $this->getEditorDataAsHtmlString());
  }

  /**
   * Test adding a quote via the Helfi quote plugin.
   */
  protected function assertAddingQuote(): void {
    // Initialize the CKEditor with a suitable markup.
    $this->initializeEditor('<p>Test content</p>');

    /** @var \Drupal\FunctionalJavascriptTests\WebDriverWebAssert $assert_session */
    $assert_session = $this->assertSession();

    // Select the paragraph.
    $this->selectTextInsideElement('.ck-content p');

    // Open the quote dialog.
    $this->pressEditorButton('Add a quote');

    // Check that the textarea is prefilled with the selected text.
    $quote_textarea = $assert_session->waitForElementVisible('css', '.helfi-quote .ck-helfi-textarea');
    $this->assertSame('Test content', $quote_textarea->getValue());

    // Check that the author field is empty and fill in the author field.
    $author_field = $assert_session->waitForElementVisible('css', '.helfi-quote .ck-input-text');
    $this->assertEmpty($author_field->getValue());
    $author_field->setValue('Test author');

    // Save the quote.
    $this->pressEditorButton('Save');

    // Check that the quote element structure is correct.
    $quote = $assert_session->waitForElementVisible('css', '.ck-editor__main>.ck-content blockquote');
    $this->assertSame('Test content', $quote->find('css', 'p[data-helfi-quote-text]')->getText());
    $this->assertSame('Test author', $quote->find('css', 'footer[data-helfi-quote-author]')->getText());
  }

  /**
   * Test adding a table via the Helfi table plugin.
   *
   * The helfiTable plugin adds a tabindex 0 to the <figure> element.
   * Test for the tabindex 0 existence.
   */
  protected function assertAddingTable(): void {
    $page = $this->getSession()->getPage();

    $cell = 'Test content';
    $caption = 'Caption';
    $content = '<figure class="table"><table><tbody><tr><td>' . $cell . '</td></tr></tbody></table> <figcaption>' . $caption . '</figcaption></figure>';

    // Initialize the CKEditor with a suitable markup.
    $this->initializeEditor($content);

    /** @var \Drupal\FunctionalJavascriptTests\WebDriverWebAssert $assert_session */
    $assert_session = $this->assertSession();

    // Check that the figure element exists and it has tabindex 0.
    $table_container = $assert_session->waitForElementVisible('css', '.ck-editor__main>.ck-content figure.table');
    $this->assertNotNull($table_container);
    $this->assertSame('0', $table_container->getAttribute('tabindex'));

    // Check that the caption is present.
    $figcaption = $page->find('css', 'figure.table > figcaption');
    $this->assertEquals($caption, $figcaption->getText());

    // Check that the table and the cell content is present.
    $table = $page->find('css', 'figure.table > table');
    $this->assertEquals($cell, $table->getText());
  }

  /**
   * Test the link text space cleanup.
   */
  protected function assertLinkSpaces(): void {
    // Spaces at the end of a link text are moved after the link.
    $this->initializeEditor('<p><a href="https://www.hel.fi">Link text. </a>Text after the link</p>');
    $this->assertStringContainsString('>Link text.</a> Text after the link</p>', $this->getEditorDataAsHtmlString());

    // Remove spaces at the end of a link text when a space follows.
    $this->initializeEditor('<p><a href="https://www.hel.fi">Link text. </a> Text after the link</p>');
    $this->assertStringContainsString('>Link text.</a> Text after the link</p>', $this->getEditorDataAsHtmlString());

    // Spaces at the start of a link text are moved before the link.
    $this->initializeEditor('<p>Text before the link<a href="https://www.hel.fi"> Link text.</a></p>');
    $this->assertMatchesRegularExpression('#<p>Text before the link <a [^>]+>Link text\.</a></p>#', $this->getEditorDataAsHtmlString());

    // Remove spaces at the start of a link text when a space precedes.
    $this->initializeEditor('<p>Text before the link <a href="https://www.hel.fi"> Link text.</a></p>');
    $this->assertMatchesRegularExpression('#<p>Text before the link <a [^>]+>Link text\.</a></p>#', $this->getEditorDataAsHtmlString());

    // Spaces at both edges of a link text are moved outside the link.
    $this->initializeEditor('<p>Read<a href="https://www.hel.fi"> Link text. </a>Text after the link</p>');
    $this->assertMatchesRegularExpression('#<p>Read <a [^>]+>Link text\.</a> Text after the link</p>#', $this->getEditorDataAsHtmlString());

    // Spaces at the edges of a pasted link text are moved outside the link.
    $this->initializeEditor('');
    $this->pasteContent('<p>Read<a href="https://www.hel.fi"> Link text. </a>Text after the link</p>');
    $this->assertMatchesRegularExpression('#<p>Read <a [^>]+>Link text\.</a> Text after the link</p>#', $this->getEditorDataAsHtmlString());

    // Move spaces of a pasted inline link text outside the link.
    $this->initializeEditor('');
    $this->pasteContent('Read<a href="https://www.hel.fi"> Link text. </a>Text after the link');
    $this->assertMatchesRegularExpression('#<p>Read <a [^>]+>Link text\.</a> Text after the link</p>#', $this->getEditorDataAsHtmlString());

    // Remove spaces of a pasted link text next to existing spaces.
    $this->initializeEditor('');
    $this->pasteContent('<p>Read <a href="https://www.hel.fi"> Link text. </a> Text after the link</p>');
    $this->assertMatchesRegularExpression('#<p>Read <a [^>]+>Link text\.</a> Text after the link</p>#', $this->getEditorDataAsHtmlString());
  }

  /**
   * Test the Helfi nbsp plugin.
   */
  protected function assertNonBreakingSpaces(): void {
    // Non-breaking spaces the editor didn't add on purpose should be
    // converted to regular spaces.
    $this->initializeEditor('<p>Test&nbsp;content</p>');
    $this->assertSame('<p>Test content</p>', $this->getEditorDataAsHtmlString());

    // Repeated spaces are collapsed to space.
    $this->initializeEditor('<p>Test &nbsp; content</p>');
    $this->assertSame('<p>Test content</p>', $this->getEditorDataAsHtmlString());

    // Span without the data-nbsp, should be converted to single space.
    $this->initializeEditor('<p>Test<span>&nbsp;</span> content</p>');
    $this->assertSame('<p>Test content</p>', $this->getEditorDataAsHtmlString());

    // Spaces are removed from the start and the end of a text block.
    $this->initializeEditor('<p><span>&nbsp;</span>Test content<span>&nbsp;</span></p>');
    $this->assertSame('<p>Test content</p>', $this->getEditorDataAsHtmlString());

    // Marked non-breaking spaces are kept.
    $this->initializeEditor('<p>Test<span data-nbsp="">&nbsp;</span>content</p>');
    $this->assertSame('<p>Test<span data-nbsp="">&nbsp;</span>content</p>', $this->getEditorDataAsHtmlString());

    // Repeated non-breaking spaces are collapsed into one space.
    $this->initializeEditor('<p>Test<span data-nbsp="">&nbsp;&nbsp;&nbsp;</span>content</p>');
    $this->assertSame('<p>Test<span data-nbsp="">&nbsp;</span>content</p>', $this->getEditorDataAsHtmlString());

    // A marked non-breaking space next to a regular space is kept.
    $before_space_content = '<p>Test<span data-nbsp="">&nbsp;</span> content</p>';
    $this->initializeEditor($before_space_content);
    $this->assertSame($before_space_content, $this->getEditorDataAsHtmlString());

    // Empty paragraphs are removed from the start and the end of the content.
    $this->initializeEditor('<p>&nbsp;</p><p>Test</p><p>&nbsp;</p>');
    $this->assertSame('<p>Test</p>', $this->getEditorDataAsHtmlString());

    // Empty paragraphs are removed on load.
    $empty_paragraphs = [
      '<p>Test</p><p></p><p>Content</p>',
      '<p>Test</p><p> </p><p>Content</p>',
      '<p>Test</p><p>&nbsp;</p><p>Content</p>',
      '<p>Test</p><p><br></p><p>Content</p>',
      '<p>Test</p><p></p><p> </p><p>&nbsp;</p><p>Content</p>',
    ];

    foreach ($empty_paragraphs as $content) {
      $this->initializeEditor($content);
      $this->assertSame('<p>Test</p><p>Content</p>', $this->getEditorDataAsHtmlString(), "Empty paragraphs are removed from $content.");
    }

    // Double line breaks split the paragraph on load.
    $double_breaks = [
      '<p>Test text<br><br>Another text</p>',
      '<p>Test text<br> <br>Another text</p>',
      '<p>Test text<br /><br />Another text</p>',
      '<p>Test text<br /> <br />Another text</p>',
      '<p>Test text<br>&nbsp;<br>Another text</p>',
      '<p>Test text <br /> <br /> Another text</p>',
      '<p>Test text<br><br><br>Another text</p>',
    ];

    foreach ($double_breaks as $content) {
      $this->initializeEditor($content);
      $this->assertSame('<p>Test text</p><p>Another text</p>', $this->getEditorDataAsHtmlString(), "Double line breaks split the paragraph in $content.");
    }

    // Every double line break in a paragraph splits it.
    $this->initializeEditor('<p>First<br /> <br />Second<br><br>Third</p>');
    $this->assertSame('<p>First</p><p>Second</p><p>Third</p>', $this->getEditorDataAsHtmlString());

    // Line breaks are removed from the start and the end of a paragraph.
    $this->initializeEditor('<p><br><br>Test text<br /> <br /></p>');
    $this->assertSame('<p>Test text</p>', $this->getEditorDataAsHtmlString());

    // A single line break between text is kept.
    $single_break_content = '<p>Test text<br>Another text</p>';
    $this->initializeEditor($single_break_content);
    $this->assertSame($single_break_content, $this->getEditorDataAsHtmlString());

    // A second line break splits the paragraph.
    foreach (['', "editor.execute('insertText', { text: ' ' });"] as $between) {
      $this->initializeEditor('<p>Test text</p>');
      $javascript = <<<JS
(function(){
  const editor = Drupal.CKEditor5Instances.get(Drupal.CKEditor5Instances.keys().next().value);
  const paragraph = editor.model.document.getRoot().getChild(0);
  editor.model.change((writer) => writer.setSelection(writer.createPositionAt(paragraph, 'end')));
  editor.execute('shiftEnter');
  $between
  editor.execute('shiftEnter');
  editor.execute('insertText', { text: 'Another text' });
})();
JS;
      $this->getSession()->executeScript($javascript);
      $this->assertSame('<p>Test text</p><p>Another text</p>', $this->getEditorDataAsHtmlString(), 'A second line break splits the paragraph.');
    }

    // Spaces next to the split are removed.
    foreach ([strlen('Text before'), strlen('Text before ')] as $offset) {
      $this->initializeEditor('<p>Text before text after</p>');
      $javascript = <<<JS
(function(){
  const editor = Drupal.CKEditor5Instances.get(Drupal.CKEditor5Instances.keys().next().value);
  const paragraph = editor.model.document.getRoot().getChild(0);
  editor.model.change((writer) => writer.setSelection(writer.createPositionAt(paragraph, $offset)));
  editor.execute('shiftEnter');
  editor.execute('shiftEnter');
})();
JS;
      $this->getSession()->executeScript($javascript);
      $this->assertSame('<p>Text before</p><p>text after</p>', $this->getEditorDataAsHtmlString(), "Spaces next to the split at offset $offset are removed.");
    }

    // Add double line breaks and empty paragraphs.
    $this->initializeEditor('<p>Test text</p>');
    $javascript = <<<JS
(function(){
  const editor = Drupal.CKEditor5Instances.get(Drupal.CKEditor5Instances.keys().next().value);
  const paragraph = editor.model.document.getRoot().getChild(0);
  editor.model.change((writer) => {
    writer.insertElement('softBreak', paragraph, 'end');
    writer.insertElement('softBreak', paragraph, 'end');
    writer.insertText('Another text', paragraph, 'end');
    writer.setSelection(writer.createPositionAt(paragraph, 'end'));
  });
  editor.execute('enter');
  editor.execute('enter');
})();
JS;
    $this->getSession()->executeScript($javascript);
    $this->assertSame('<p>Test text<br><br>Another text</p><p>&nbsp;</p><p>&nbsp;</p>', $this->getEditorDataAsHtmlString());

    // Double line breaks and empty paragraphs are cleaned up on blur.
    $this->blurEditor();
    $this->assertSame('<p>Test text</p><p>Another text</p>', $this->getEditorDataAsHtmlString());

    // Empty paragraphs and double line breaks are cleaned up on paste.
    $this->initializeEditor('');
    $this->pasteContent('<p>Test</p><p></p><p> </p><p>&nbsp;</p><p>Test text<br /> <br />Another text</p><p><br><br></p><p>Content</p>');
    $this->assertSame('<p>Test</p><p>Test text</p><p>Another text</p><p>Content</p>', $this->getEditorDataAsHtmlString());

    // Pasted inline double line breaks split the paragraph.
    $this->initializeEditor('');
    $this->pasteContent('Test text<br>&nbsp;<br>Another text');
    $this->assertSame('<p>Test text</p><p>Another text</p>', $this->getEditorDataAsHtmlString());

    // An inserted non-breaking space is wrapped in <span data-nbsp>.
    $this->initializeEditor('<p>Testcontent</p>');
    $this->insertNonBreakingSpace(strlen('Test'));
    $this->assertSame('<p>Test<span data-nbsp="">&nbsp;</span>content</p>', $this->getEditorDataAsHtmlString());
  }

  /**
   * Paste the content to the editor.
   *
   * @param string $html
   *   The pasted html.
   */
  protected function pasteContent(string $html): void {
    $html = json_encode($html);
    $javascript = <<<JS
(function(){
  const dataTransfer = new DataTransfer();
  dataTransfer.setData('text/html', $html);
  const event = new ClipboardEvent('paste', { clipboardData: dataTransfer, bubbles: true, cancelable: true });
  document.querySelector('.ck-editor__editable').dispatchEvent(event);
})();
JS;
    $this->getSession()->executeScript($javascript);
  }

  /**
   * Move the focus out of the editor.
   */
  protected function blurEditor(): void {
    $this->getSession()->getPage()->findField('title[0][value]')->focus();

    $javascript = <<<JS
(function(){
  const editor = Drupal.CKEditor5Instances.get(Drupal.CKEditor5Instances.keys().next().value);
  return !editor.ui.focusTracker.isFocused;
})();
JS;
    $this->assertTrue($this->getSession()->wait(5000, $javascript), 'The editor loses focus.');
  }

  /**
   * Insert a non-breaking space to the first paragraph.
   *
   * @param int $offset
   *   The position to insert the non-breaking space to.
   */
  protected function insertNonBreakingSpace(int $offset): void {
    $javascript = <<<JS
(function(){
  const editor = Drupal.CKEditor5Instances.get(Drupal.CKEditor5Instances.keys().next().value);
  const paragraph = editor.model.document.getRoot().getChild(0);
  editor.model.change((writer) => writer.setSelection(writer.createPositionAt(paragraph, $offset)));
  editor.execute('insertText', { text: '\u00A0' });
})();
JS;
    $this->getSession()->executeScript($javascript);
  }

  /**
   * Select language from language selector.
   *
   * @param string $langcode
   *   The language code as a string.
   */
  protected function selectLanguage(string $langcode): void {
    // Open the language selector dialog.
    $this->pressEditorButton('Select language');

    /** @var \Drupal\FunctionalJavascriptTests\WebDriverWebAssert $assert_session */
    $assert_session = $this->assertSession();

    // Check that the language selection dialog field is visible and click it.
    $language_selector = $assert_session->waitForElementVisible('css', '.helfi-language-selector .ts-control');
    $this->assertTrue($language_selector->isVisible(), 'Language selector is not visible.');
    $language_selector->click();

    // Wait for the language option to be visible and click it.
    $language = $assert_session->waitForElementVisible('css', '.helfi-language-selector .ts-dropdown span[data-value="' . $langcode . '"]');
    $this->assertNotNull($language, strtoupper($langcode) . ' language selection is not visible.');
    $language->click();
  }

  /**
   * Unselect language from language selector.
   */
  protected function unSelectLanguage(): void {
    // Open the language selector dialog.
    $this->pressEditorButton('Select language');

    /** @var \Drupal\FunctionalJavascriptTests\WebDriverWebAssert $assert_session */
    $assert_session = $this->assertSession();

    // Check that the language selection dialog field is visible and click it.
    $remove_language = $assert_session->waitForElementVisible('css', '.helfi-language-selector a.remove');
    $this->assertTrue($remove_language->isVisible(), 'The language remove button is not visible.');
    $this->getSession()->executeScript('document.querySelector(".helfi-language-selector a.remove").click();');
  }

  /**
   * Initialize CKEditor 5 editor with given content.
   *
   * @param string $content
   *   The content to be edited.
   *
   * @return \Drupal\Core\Url
   *   The edit URL of the created node.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function initializeEditor(string $content): Url {
    /** @var \Drupal\FunctionalJavascriptTests\WebDriverWebAssert $assert_session */
    $assert_session = $this->assertSession();

    // Create page node and edit it.
    $edit_url = $this->drupalCreateNode([
      'type' => 'page',
      'body' => [
        'value' => $content,
        'format' => 'full_html',
      ],
    ])->toUrl('edit-form');
    $this->drupalGet($edit_url);

    // Focus the editable area first.
    $content_area = $assert_session->waitForElementVisible('css', '.ck-editor__editable');
    $content_area->click();

    // Wait for CKEditor to load.
    $this->waitForEditor();
    return $edit_url;
  }

  /**
   * Asserts a particular balloon is visible.
   *
   * @param string $balloon_content_selector
   *   A CSS selector.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The asserted balloon.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  protected function assertVisibleBalloon(string $balloon_content_selector): NodeElement {
    $this->assertSession()->elementExists('css', '.ck-balloon-panel_visible');
    $selector = ".ck-balloon-panel_visible .ck-balloon-rotator__content > .ck$balloon_content_selector";
    $this->assertSession()->elementExists('css', $selector);
    return $this->getSession()->getPage()->find('css', $selector);
  }

}
