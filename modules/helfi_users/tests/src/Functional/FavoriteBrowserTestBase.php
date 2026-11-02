<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_users\Functional;

use Behat\Mink\Element\NodeElement;
use Drupal\Tests\helfi_api_base\Functional\BrowserTestBase;
use Drupal\user\UserInterface;

/**
 * Base class for favorite functional tests.
 */
abstract class FavoriteBrowserTestBase extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'language',
    'content_translation',
    'field_group',
    'helfi_users',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'helfi_users_test_theme';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalCreateContentType(['type' => 'page']);
    $this->container->get('content_translation.manager')->setEnabled('node', 'page', TRUE);
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();
  }

  /**
   * Creates and logs in a content editor who can use favorites.
   *
   * @return \Drupal\user\UserInterface
   *   The editor.
   */
  protected function loginEditor(): UserInterface {
    $user = $this->drupalCreateUser([
      'access content',
      'create page content',
      'edit any page content',
      'use favorites',
    ]);
    $this->drupalLogin($user);
    return $user;
  }

  /**
   * Clicks the favorite toggle.
   *
   * @param \Behat\Mink\Element\NodeElement $toggle
   *   The favorite toggle.
   *
   * @return string
   *   The swapped toggle markup.
   */
  protected function clickFavoriteToggle(NodeElement $toggle): string {
    $path = $toggle->getAttribute('data-hx-post');
    $this->assertNotNull($path, 'The favorite toggle has a post URL.');

    $response = $this->getHttpClient()->request('POST', $this->getAbsoluteUrl($path), [
      'cookies' => $this->getSessionCookies(),
      'http_errors' => FALSE,
    ]);
    $this->assertEquals(200, $response->getStatusCode(), 'The toggle request succeeds.');

    return (string) $response->getBody();
  }

}
