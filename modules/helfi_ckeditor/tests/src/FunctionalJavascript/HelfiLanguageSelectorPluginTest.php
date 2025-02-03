<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ckeditor\FunctionalJavascript;

use Drupal\Core\Entity\EntityMalformedException;
use Drupal\FunctionalJavascriptTests\WebDriverWebAssert;
use Drupal\Tests\ckeditor5\Traits\CKEditor5TestTrait;
use Drupal\Tests\helfi_ckeditor\HelfiCKEditor5TestBase;

/**
 * Tests CKEditor 5 Helfi language selector plugin.
 *
 * @group helfi_ckeditor
 */
class HelfiLanguageSelectorPluginTest extends HelfiCKEditor5TestBase {

  use CKEditor5TestTrait;

  /**
   * Test selecting a language from the Helfi language selector plugin.
   */
  public function testLanguageSelection(): void {
    /** @var \Drupal\FunctionalJavascriptTests\WebDriverWebAssert $assert_session */
    $assert_session = $this->assertSession();

    try {
      $this->initializeEditor('<p>Test</p><p>Testi</p><p>امتحان</p>');
    }
    catch (EntityMalformedException $e) {
      $this->fail($e->getMessage());
    }

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
  public function testLanguageUnSelection(): void {
    /** @var \Drupal\FunctionalJavascriptTests\WebDriverWebAssert $assert_session */
    $assert_session = $this->assertSession();
    $test_content = '<p><span lang="en" dir="ltr">Test</span></p><p><span lang="fi" dir="ltr">Testi</span></p><p><span lang="ar" dir="rtl">امتحان</span></p>';

    try {
      $this->initializeEditor($test_content);
    }
    catch (EntityMalformedException $e) {
      $this->fail($e->getMessage());
    }

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
   * Select language from language selector.
   *
   * @param string $langcode
   *   The language code as a string.
   */
  protected function selectLanguage(string $langcode): void {
    /** @var \Drupal\FunctionalJavascriptTests\WebDriverWebAssert $assert_session */
    $assert_session = $this->assertSession();

    // Open the language selector dialog.
    $this->pressEditorButton('Select language');

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
    /** @var \Drupal\FunctionalJavascriptTests\WebDriverWebAssert $assert_session */
    $assert_session = $this->assertSession();

    // Open the language selector dialog.
    $this->pressEditorButton('Select language');

    // Check that the language selection dialog field is visible and click it.
    $remove_language = $assert_session->waitForElementVisible('css', '.helfi-language-selector a.remove');
    $this->assertTrue($remove_language->isVisible(), 'The language remove button is not visible.');
    $remove_language->click();
  }

}
