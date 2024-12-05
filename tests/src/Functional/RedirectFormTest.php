<?php

namespace src\Functional;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\helfi_platform_config\Entity\PublishableRedirect;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests `redirect.add` form.
 */
class RedirectFormTest extends BrowserTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'redirect',
    'helfi_platform_config',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $user = $this->createUser([
      'administer redirects',
    ]);
    $this->drupalLogin($user);
  }

  /**
   * Tests that saving redirect from entity form sets the custom field to TRUE.
   */
  public function testRedirectForm() {
    $edit = [
      'redirect_source[0][path]' => 'test',
      'redirect_redirect[0][uri]' => '<front>',
      'status_code' => 307,
    ];

    $this->drupalGet(Url::fromRoute('redirect.add')->toString());
    $this->submitForm($edit, 'Save');

    $redirects = $this->container
      ->get(EntityTypeManagerInterface::class)
      ->getStorage('redirect')
      ->loadByProperties([
        'redirect_source' => 'test',
      ]);

    $this->assertNotEmpty($redirects);
    $redirect = reset($redirects);
    $this->assertInstanceOf(PublishableRedirect::class, $redirect);

    // Redirect created from entity form should be marked as custom.
    $this->assertTrue($redirect->isCustom());
  }

}
