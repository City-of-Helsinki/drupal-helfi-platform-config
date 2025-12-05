<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\ExistingSite;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\helfi_platform_config\Entity\PublishableRedirect;
use Drupal\Tests\helfi_api_base\Functional\ExistingSiteTestBase;

/**
 * Tests `redirect.add` form.
 *
 * @group helfi_platform_config
 */
class RedirectFormTest extends ExistingSiteTestBase {

  /**
   * Tests that saving redirect from entity form sets the custom field to TRUE.
   */
  public function testRedirectForm() {
    $user = $this->createUser([
      'administer redirects',
    ]);
    $this->drupalLogin($user);

    $edit = [
      'redirect_source[0][path]' => 'test',
      'redirect_redirect[0][uri]' => '<front>',
      'status_code' => 307,
    ];

    $this->drupalGet(Url::fromRoute('redirect.add'));
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
