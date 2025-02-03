<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ckeditor\FunctionalJavascript;

use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Tests\helfi_ckeditor\HelfiCKEditor5TestBase;

/**
 * Tests CKEditor 5 Helfi quote plugin.
 *
 * @group helfi_ckeditor
 */
class HelfiQuotePluginTest extends HelfiCKEditor5TestBase {

  /**
   * Test adding a quote via the Helfi quote plugin.
   */
  public function testAddingQuote(): void {
    /** @var \Drupal\FunctionalJavascriptTests\WebDriverWebAssert $assert_session */
    $assert_session = $this->assertSession();

    try {
      $this->initializeEditor('<p>Test content</p>');
    }
    catch (EntityMalformedException $e) {
      $this->fail($e->getMessage());
    }

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

}
