<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ckeditor\FunctionalJavascript;

use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Tests\helfi_ckeditor\HelfiCKEditor5TestBase;

/**
 * Tests CKEditor 5 Helfi plugins.
 *
 * @group helfi_ckeditor
 */
class HelfiCKEditorPluginTests extends HelfiCKEditor5TestBase {

  /**
   * Tests CKEditor 5 custom plugins.
   */
  public function testHelfiPlugins(): void {
    $this->assertLinkPlugin();
    $this->assertLanguageSelection();
    $this->assertLanguageUnSelection();
    $this->assertAddingQuote();
    $this->assertAddingTable();
  }

  /**
   * Tests CKEditor 5 Helfi link plugin.
   */
  protected function assertLinkPlugin(): void {
    $test_url = 'https://www.hel.fi';

    try {
      $this->initializeEditor('');
    }
    catch (EntityMalformedException $e) {
      $this->fail($e->getMessage());
    }

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
    $protocol_select_list = $balloon->find('css', 'div.ck-helfi-link-select-list');
    $https_selection = $protocol_select_list->find('css', 'span[data-value="https"]');
    $this->assertTrue($https_selection->isVisible(), 'HTTPS selection is not visible.');
    $https_selection->click();

    // Check that the protocol field is not visible after adding href.
    $this->assertFalse($protocol_field->isVisible(), 'Protocol field is not visible.');

    // Find the href field.
    // Note. There is no class for the field which indicates that it is a link
    // field, so we'll use the only input text field css class as a target as
    // there is no other text fields in the CKEditor balloon.
    $link_field = $balloon->find('css', '.ck-input-text');

    // Check that there is a value in the href field,
    // set by the protocol selection.
    $this->assertNotEmpty($link_field);

    // Override the href field value with a URL with a space at the end.
    $link_field->setValue($test_url . ' ');

    // Check that the protocol field is not visible after adding href.
    $this->assertFalse($protocol_field->isVisible(), 'Protocol field is not visible.');

    // Open the details summary.
    $details = $assert_session->waitForElementVisible('css', 'details.ck-helfi-link-details');
    $details->find('css', '.ck-helfi-link-details__summary')->click();

    // Select the "open the link in new window" option and confirm it.
    $link_new_window = $details->find('css', '.helfi-link--link-new-window');
    $this->assertTrue($link_new_window->isVisible(), 'Link new window is visible.');
    $details->find('css', 'input#link-new-window')->check();

    $link_new_window_confirmed = $details->find('css', '.helfi-link--link-new-window-confirm');
    $this->assertTrue($link_new_window_confirmed->isVisible(), 'Link new window confirmed is visible.');
    $details->find('css', 'input#link-new-window-confirm')->check();

    // Open the select list of styles and click it to make the options visible.
    $button_selection_select_list = $details->find('css', 'div.ck-helfi-link-select-list');
    $ts_control = $button_selection_select_list->find('css', '#variant-ts-control');
    $this->assertTrue($ts_control->isVisible(), 'Controls are visible.');
    $ts_control->click();

    // Select the primary button style.
    $primary_button = $button_selection_select_list->find('css', 'span[data-value="primary"]');
    $this->assertTrue($primary_button->isVisible(), 'Primary button is visible.');
    $primary_button->click();

    // Save the link.
    $balloon->pressButton('Save');

    // Assert balloon was closed by pressing its "Save" button.
    $this->assertTrue($assert_session->waitForElementRemoved('css', '.ck-button-save'));

    // Check for Link plugin existence.
    $this->assertEditorButtonEnabled('Link');

    // Make sure all attributes are populated.
    $linkit_link = $assert_session->waitForElementVisible('css', '.ck-content a');
    $this->assertNotNull($linkit_link);
    $this->assertSame($test_url, $linkit_link->getAttribute('href'));
    $this->assertSame('button', $linkit_link->getAttribute('data-hds-component'));
    $this->assertSame('_blank', $linkit_link->getAttribute('target'));
    $this->assertSame($test_url, $linkit_link->getText());

    // Test to remove all attributes and check that they are removed.
    $linkit_link->click();

    // Open the link action balloon and click "Edit link".
    $link_action_balloon = $this->assertVisibleBalloon('.ck-link-actions');
    $link_action_balloon->pressButton('Edit link');

    // Check that the CKEditor 5 balloon (dialog) is visible.
    $balloon = $this->assertVisibleBalloon('.ck-link-form');

    // Check that there is a value in the href field.
    $link_field = $balloon->find('css', '.ck-input-text');
    $this->assertNotEmpty($link_field);

    // Override the href field value with a #test value.
    $link_field->setValue('#test');

    // Open the details summary and remove the previously selected options.
    $edit_details = $assert_session->waitForElementVisible('css', 'details.ck-helfi-link-details');
    $edit_details->find('css', '.ck-helfi-link-details__summary')->click();
    $details->find('css', 'input#link-new-window')->uncheck();
    $details->find('css', 'input#link-new-window-confirm')->uncheck();

    // Open the select list of styles and click it to make the options visible.
    $button_selection_select_list = $details->find('css', 'div.ck-helfi-link-select-list');
    $ts_control = $button_selection_select_list->find('css', '#variant-ts-control');
    $this->assertTrue($ts_control->isVisible(), 'Controls are visible.');
    $ts_control->click();

    // Select the normal link style.
    $link_button = $button_selection_select_list->find('css', 'span[data-value="link"]');
    $this->assertTrue($link_button->isVisible(), 'Normal link option is visible.');
    $link_button->click();

    // Save the link.
    $balloon->pressButton('Save');

    // Assert that the link has correct attributes.
    $linkit_link = $assert_session->waitForElementVisible('css', '.ck-content a');
    $this->assertNotNull($linkit_link);
    $this->assertSame('#test', $linkit_link->getAttribute('href'));
    $this->assertNotSame('button', $linkit_link->getAttribute('data-hds-component'));
    $this->assertNotSame('_blank', $linkit_link->getAttribute('target'));
  }

  /**
   * Test selecting a language from the Helfi language selector plugin.
   */
  protected function assertLanguageSelection(): void {
    try {
      $this->initializeEditor('<p>Test</p><p>Testi</p><p>امتحان</p>');
    }
    catch (EntityMalformedException $e) {
      $this->fail($e->getMessage());
    }

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

    try {
      $this->initializeEditor($test_content);
    }
    catch (EntityMalformedException $e) {
      $this->fail($e->getMessage());
    }

    /** @var \Drupal\FunctionalJavascriptTests\WebDriverWebAssert $assert_session */
    $assert_session = $this->assertSession();

    // Go through the test content and select a language for each paragraph.
    foreach (['en' => 1, 'fi' => 2, 'ar' => 3] as $index) {
      // Select the example text and choose a language for it.
      $this->selectTextInsideElement('.ck-content p:nth-of-type(' . $index . ')');
      $this->unSelectLanguage();
      $non_translated_paragraph = $assert_session->waitForElementVisible('css', '.ck-editor__main>.ck-content p:nth-of-type(' . $index . ')');
      $this->assertNotNull($non_translated_paragraph);
      $this->assertNull($non_translated_paragraph->find('css', 'span'));
    }
  }

  /**
   * Test adding a quote via the Helfi quote plugin.
   */
  protected function assertAddingQuote(): void {
    try {
      $this->initializeEditor('<p>Test content</p>');
    }
    catch (EntityMalformedException $e) {
      $this->fail($e->getMessage());
    }

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

    try {
      $this->initializeEditor($content);
    }
    catch (EntityMalformedException $e) {
      $this->fail($e->getMessage());
    }

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

    // Choose language for the English text.
    $language_selections = $assert_session->waitForElementVisible('css', '.helfi-language-selector .ts-dropdown');
    $language = $language_selections->find('css', 'span[data-value="' . $langcode . '"]');
    $this->assertTrue($language->isVisible(), strtoupper($langcode) . ' language selection is not visible.');
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
    $remove_language->click();
  }

}
