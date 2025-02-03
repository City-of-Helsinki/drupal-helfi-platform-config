<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ckeditor\FunctionalJavascript;

use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Tests\helfi_ckeditor\HelfiCKEditor5TestBase;

/**
 * Tests CKEditor 5 Helfi table plugin.
 *
 * @group helfi_ckeditor
 */
class HelfiTablePluginTest extends HelfiCKEditor5TestBase {

  /**
   * Test adding a table via the Helfi table plugin.
   *
   * The helfiTable plugin adds a tabindex 0 to the <figure> element.
   * Test for the tabindex 0 existence.
   */
  public function testAddingTable(): void {
    /** @var \Drupal\FunctionalJavascriptTests\WebDriverWebAssert $assert_session */
    $assert_session = $this->assertSession();
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

}
