<?php

namespace Drupal\Tests\hdbt_cookie_banner\FunctionalJavascript;

use Behat\Mink\Driver\Selenium2Driver;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests the functionality of the JavaScript cookie banner.
 *
 * @group hdbt_cookie_banner
 */
class CookieBannerTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'hdbt_cookie_banner',
    'hdbt_cookie_banner_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Setup the test environment.
   */
  protected function setUp(): void {
    parent::setUp();

    // Get the path to the JSON file.
    $module_path = \Drupal::service('extension.list.module')
      ->getPath('hdbt_cookie_banner');
    $json_file_path = $module_path . '/assets/json/siteSettingsTemplate.json';

    // Assert the file exists.
    $this->assertTrue(file_exists($json_file_path));

    // Load and decode the JSON.
    $json_content = file_get_contents($json_file_path);
    $this->assertNotEmpty($json_content, 'Decoded JSON data is not empty.');

    // Get the public base URL (in a FunctionalJavascript test).
    // Construct a URL for the hds-cookie-consent.min.js file.
    $cookie_js_url = "/$module_path/assets/js/hds-cookie-consent.min.js";

    // Change configuration value before the test runs.
    $config = $this->config('hdbt_cookie_banner.settings');
    $config
      ->set('use_custom_settings', TRUE)
      ->set('site_settings', $json_content)
      ->set('use_custom_settings', TRUE)
      ->set('use_internal_hds_cookie_js', FALSE)
      ->set('hds_cookie_js_override', $cookie_js_url)
      ->save();

    \Drupal::service('cache.default')->deleteAll();
  }

  /**
   * Tests the cookie banner visibility and interaction.
   */
  public function testCookieBanner() {
    $this->drupalGet('/test-page');
    $this->assertSession()->pageTextContains('Test Content');
    $this->assertSession()->elementExists('css', '.test-footer');

    // Get the web driver.
    $driver = $this->getSession()->getDriver();

    // Check if the driver is an instance of DrupalSelenium2Driver.
    if ($driver instanceof Selenium2Driver) {
      // Get all cookies from the browser.
      $cookies = $driver->getWebDriverSession()->getCookie();

      // Extract only the 'name' keys from all the cookies, as we want to check
      // if a specific cookie "change-me" exists.
      $cookieNames = array_column($cookies, 'name');
      $this->assertNotContains(
        'change-me',
        $cookieNames,
        'The cookie "change-me" was found, but it should not exist.',
      );
    }
    else {
      $this->fail('The driver is not an instance of Selenium2Driver.');
    }

    // Assert that the cookie banner is visible and click the accept button.
    $this->assertCookieBannerIsVisible();

    // Get the new cookies from the browser.
    $new_cookies = $driver->getWebDriverSession()->getCookie();

    // There should be a new cookie called "change-me".
    $cookieNames = array_column($new_cookies, 'name');
    $this->assertContains(
      'change-me',
      $cookieNames,
      'The cookie "change-me" was not found after clicking the button.',
    );

    // Reload the page and assert that the cookie banner is not visible.
    $this->drupalGet('/test-page');
    $this->assertSession()->pageTextContains('Test Content');
    $this->assertCookieBannerNotVisible();
  }

  /**
   * Asserts that the cookie banner is visible.
   */
  protected function assertCookieBannerIsVisible() {
    // Get the Shadow DOM host and button selectors.
    $shadowHostSelector = '.hds-cc__target';
    $buttonSelector = '.hds-cc__all-cookies-button';

    // Verify that the cookie banner is visible and click the accept button.
    $js = <<<JS
  const shadowHost = document.querySelector('$shadowHostSelector');
  if (!shadowHost) {
    throw new Error('Shadow host not found.');
  }
  const shadowRoot = shadowHost.shadowRoot;
  if (!shadowRoot) {
    throw new Error('Shadow root is not attached.');
  }
  const button = shadowRoot.querySelector('$buttonSelector');
  if (!button) {
    throw new Error('Button not found inside the shadow DOM.');
  }
  button.click();
JS;

    // Execute the JavaScript in the browser context.
    $this->getSession()->executeScript($js);
  }

  /**
   * Asserts that the cookie banner is not visible.
   */
  protected function assertCookieBannerNotVisible() {
    // Get the Shadow DOM host and button selectors.
    $shadowHostSelector = '.hds-cc__target';

    // Verify that the cookie banner is not visible.
    $js = <<<JS
  const shadowHost = document.querySelector('$shadowHostSelector');
  if (shadowHost) {
    throw new Error('Shadow host still found.');
  }
JS;
    // Execute the JavaScript in the browser context.
    $this->getSession()->executeScript($js);
  }

}
