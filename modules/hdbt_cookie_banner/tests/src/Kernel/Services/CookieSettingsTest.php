<?php

declare(strict_types=1);

namespace Drupal\Tests\hdbt_cookie_banner\Kernel\Services;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;
use Drupal\hdbt_cookie_banner\Services\CookieSettings;
use Drupal\Tests\hdbt_cookie_banner\Kernel\KernelTestBase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Route;

/**
 * Tests the CookieSettings service.
 *
 * @group hdbt_cookie_banner
 */
class CookieSettingsTest extends KernelTestBase {

  /**
   * The CookieSettings service.
   *
   * @var \Drupal\hdbt_cookie_banner\Services\CookieSettings
   */
  protected CookieSettings $cookieSettings;

  /**
   * The language interface.
   *
   * @var \Drupal\Core\Language\LanguageInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected LanguageInterface|MockObject $language;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create the CookieSettings service with mocked dependencies.
    $this->cookieSettings = new CookieSettings(
      $this->container->get('router.route_provider'),
      $this->container->get('config.factory'),
      $this->container->get('language_manager'),
      $this->container->get('extension.list.module'),
      $this->container->get('helfi_api_base.environment_resolver'),
      $this->container->get('url_generator'),
    );

    $this->language = $this->createMock(LanguageInterface::class);
    $this->language->expects($this->any())
      ->method('getId')
      ->willReturn('en');

    $this->languageManager->expects($this->any())
      ->method('getDefaultLanguage')
      ->willReturn($this->language);
  }

  /**
   * Tests getCookieBannerApiUrl with custom settings.
   */
  public function testGetCookieBannerApiUrlWithDefaultSettings(): void {
    // Expected settings for external (hel.fi) setup.
    $expected = [
      ['site_settings', ''],
      ['use_custom_settings', ''],
    ];

    // Set up the configurations with specified settings.
    $this->setUpTheConfigurations($expected);

    // Test the getCookieBannerApiUrl() with the specified settings.
    $url = $this->cookieSettings->getCookieBannerApiUrl();
    $this->assertEquals('https://www.test.hel.ninja/en/api/cookie-banner', $url);
  }

  /**
   * Tests getCookieBannerApiUrl with custom settings.
   */
  public function testGetCookieBannerApiUrlWithCustomSettings(): void {
    // Expected settings for external (hel.fi) setup.
    $expected = [
      ['site_settings', '{"test": "true"}'],
      ['use_custom_settings', TRUE],
    ];

    // Set up the configurations with specified settings.
    $this->setUpTheConfigurations($expected);

    // Mock the generateFromRoute() method of UrlGeneratorInterface.
    $this->urlGenerator->expects($this->once())
      ->method('generateFromRoute')
      ->with(
        'hdbt_cookie_banner.site_settings',
        [],
        ['language' => $this->language],
      )
      ->willReturn('/en/api/cookie-banner');

    // Test the getCookieBannerApiUrl() with the specified settings.
    $url = $this->cookieSettings->getCookieBannerApiUrl();
    $this->assertEquals('/en/api/cookie-banner', $url);
  }

  /**
   * Tests injectBannerJavaScript.
   */
  public function testInjectBannerJavaScript(): void {
    $this->moduleExtensionList->expects($this->any())
      ->method('getPath')
      ->with('hdbt_cookie_banner')
      ->willReturn('path/to/module');

    $attachments = ['#attached' => []];
    $this->cookieSettings->injectBannerJavaScript($attachments);

    $this->assertNotEmpty($attachments['#attached']['html_head']);
    $this->assertEquals('https://www.test.hel.ninja/etusivu-assets/path/to/module/assets/js/hds-cookie-consent.min.js', $attachments['#attached']['html_head'][0][0]['#attributes']['src']);
  }

  /**
   * Tests injectBannerJavaScript with library.
   */
  public function testInjectBannerJavaScriptWithLibrary(): void {
    $attachments = ['#attached' => []];
    $library = 'https://www.test.hel.ninja/my/path/to/library/hds-coockie-consent.min.js';
    $this->cookieSettings->injectBannerJavaScript($attachments, $library);

    $this->assertNotEmpty($attachments['#attached']['html_head']);
    $this->assertEquals($library, $attachments['#attached']['html_head'][0][0]['#attributes']['src']);
  }

  /**
   * Tests getCookieSettingsPageUrl when the route exists.
   */
  public function testGetCookieSettingsPageUrlRouteExists(): void {
    $route_name = 'hdbt_cookie_banner.cookie_settings_page';

    // Simulate that the route exists by not throwing an exception.
    $this->routeProvider->expects($this->once())
      ->method('getRouteByName')
      ->with($route_name)
      ->willReturn($this->createMock(Route::class));

    // Test that the URL is returned correctly.
    $url = $this->cookieSettings->getCookieSettingsPageUrl();
    $this->assertInstanceOf(Url::class, $url);
    $this->assertEquals($route_name, $url->getRouteName());
  }

  /**
   * Tests getCookieSettingsPageUrl when the route does not exist.
   */
  public function testGetCookieSettingsPageUrlRouteNotExists(): void {
    $route_name = 'hdbt_cookie_banner.cookie_settings_page';

    // Simulate that the route does not exist by throwing an exception.
    $this->routeProvider->expects($this->once())
      ->method('getRouteByName')
      ->with($route_name)
      ->willThrowException(new RouteNotFoundException());

    // Test that NULL is returned.
    $url = $this->cookieSettings->getCookieSettingsPageUrl();
    $this->assertNull($url);
  }

}
