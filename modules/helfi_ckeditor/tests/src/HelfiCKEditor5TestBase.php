<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ckeditor;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\ckeditor5\Traits\CKEditor5TestTrait;
use Drupal\user\Entity\User;

/**
 * Base class for testing CKEditor 5 Helfi plugins.
 */
class HelfiCKEditor5TestBase extends WebDriverTestBase {

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
   * Initialize CKEditor 5 editor with given content.
   *
   * @param string $content
   *   The content to be edited.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function initializeEditor(string $content): void {
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
  }

}
