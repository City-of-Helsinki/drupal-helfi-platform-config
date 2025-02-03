<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ckeditor\FunctionalJavascript;

use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Tests\helfi_ckeditor\HelfiCKEditor5TestBase;

/**
 * Tests CKEditor 5 Helfi link plugin.
 *
 * @group helfi_ckeditor
 */
class HelfiLinkPluginTest extends HelfiCKEditor5TestBase {

  /**
   * Tests CKEditor 5 Helfi link plugin.
   */
  public function test(): void {
    /** @var \Drupal\FunctionalJavascriptTests\WebDriverWebAssert $assert_session */
    $assert_session = $this->assertSession();
    $test_url = 'https://www.hel.fi';

    try {
      $this->initializeEditor('');
    }
    catch (EntityMalformedException $e) {
      $this->fail($e->getMessage());
    }

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

}
