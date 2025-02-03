<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ckeditor;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\user\Entity\User;

/**
 * Base class for testing CKEditor 5.
 *
 * @ingroup testing
 * @internal
 */
class HelfiCKEditor5TestBase extends WebDriverTestBase {

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

}
