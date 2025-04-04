<?php

declare(strict_types=1);

namespace Drupal\Tests\hdbt_cookie_banner\Kernel\Services;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;
use Drupal\Tests\hdbt_cookie_banner\Kernel\KernelTestBase;
use Drupal\hdbt_cookie_banner\Services\CookieSettings;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

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
   * An array of mock LanguageInterface objects.
   *
   * @var array
   */
  protected array $languages;

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
      $this->container->get('helfi_api_base.environment_resolver'),
      $this->container->get('url_generator'),
      $this->container->get('library.discovery'),
    );

    $this->languages = $this->setUpLanguages();

    $this->languageManager->expects($this->any())
      ->method('getDefaultLanguage')
      ->willReturn($this->languages['en']);
    $this->languageManager->expects($this->any())
      ->method('getCurrentLanguage')
      ->withAnyParameters()
      ->willReturn($this->languages['en']);
  }

  /**
   * Sets up mock LanguageInterface objects.
   *
   * @return array
   *   An array of mock LanguageInterface objects.
   */
  protected function setUpLanguages(): array {
    $language_fi = $this->createMock(LanguageInterface::class);
    $language_fi->expects($this->any())
      ->method('getId')
      ->willReturn('fi');
    $language_sv = $this->createMock(LanguageInterface::class);
    $language_sv->expects($this->any())
      ->method('getId')
      ->willReturn('sv');
    $language_en = $this->createMock(LanguageInterface::class);
    $language_en->expects($this->any())
      ->method('getId')
      ->willReturn('en');
    $language_it = $this->createMock(LanguageInterface::class);
    $language_it->expects($this->any())
      ->method('getId')
      ->willReturn('it');
    return [
      'fi' => $language_fi,
      'sv' => $language_sv,
      'en' => $language_en,
      'it' => $language_it,
    ];
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
        ['language' => $this->languages['en']],
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
    $attachments = ['#attached' => []];
    $this->cookieSettings->injectBannerJavaScript($attachments);

    $this->assertNotEmpty($attachments['#attached']['html_head']);
    $this->assertEquals('https://www.test.hel.ninja/etusivu-assets/modules/contrib/helfi_platform_config/modules/hdbt_cookie_banner/assets/js/hds-cookie-consent.min.js', $attachments['#attached']['html_head'][0][0]['#attributes']['src']);

    // Create a mock library array to return from libraryDiscovery.
    $this->libraryDiscovery->expects($this->once())
      ->method('getLibraryByName')
      ->willReturn([
        'version' => '1.2.3',
      ]);

    $attachments_with_version = ['#attached' => []];
    $this->cookieSettings->injectBannerJavaScript($attachments_with_version);
    $this->assertEquals('https://www.test.hel.ninja/etusivu-assets/modules/contrib/helfi_platform_config/modules/hdbt_cookie_banner/assets/js/hds-cookie-consent.min.js?v=1.2.3', $attachments_with_version['#attached']['html_head'][0][0]['#attributes']['src']);
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
   * Tests getCookieBannerApiUrl with custom settings.
   */
  public function testGetCookieSettingsPage(): void {
    // Expected settings for external (hel.fi) setup.
    $expected = [
      ['site_settings', '{"test": "true"}'],
      ['use_custom_settings', FALSE],
    ];

    // Set up the configurations with specified settings.
    $this->setUpTheConfigurations($expected);

    $language_map = [
      'en' => 'https://www.test.hel.ninja/en/cookie-settings',
      'fi' => 'https://www.test.hel.ninja/fi/evasteasetukset',
      'sv' => 'https://www.test.hel.ninja/sv/cookie-installningar',
      'it' => 'https://www.test.hel.ninja/en/cookie-settings',
    ];

    foreach ($language_map as $langcode => $mock_url) {
      $return_value = match($langcode) {
        'fi' => 'fi',
        'sv' => 'sv',
        default => 'en',
      };

      $this->languages[$langcode]->expects($this->any())->method('getId')->willReturn($return_value);

      // Test that the URL is returned correctly.
      $url = $this->cookieSettings->getCookieSettingsPageUrl($langcode);
      $this->assertInstanceOf(Url::class, $url);
    }
  }

  /**
   * Tests getCookieSettingsPageUrl when the route does not exist.
   */
  public function testGetCookieSettingsPageUrlRouteNotExists(): void {
    // Expected settings for external (hel.fi) setup.
    $expected = [
      ['use_custom_settings', TRUE],
    ];

    // Set up the configurations with specified settings.
    $this->setUpTheConfigurations($expected);

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

  /**
   * Tests getActiveEtusivuEnvironment with non-existent environment.
   */
  public function testGetActiveEtusivuEnvironment(): void {
    // Expected settings for external (hel.fi) setup.
    $expected = [
      ['use_custom_settings', FALSE],
    ];

    // Set up the configurations with specified settings.
    $this->setUpTheConfigurations($expected);

    // Simulate that the environment resolver returns NULL because
    // the environment has a typo or current environment is f.e. dev.
    $this->environmentResolver
      ->method('getEnvironment')
      ->willThrowException(new \InvalidArgumentException());

    // Test that the URL by route is returned.
    $url = $this->cookieSettings->getCookieSettingsPageUrl();
    $this->assertInstanceOf(Url::class, $url);

    // Simulate that the route does not exist by throwing an exception.
    $this->routeProvider->expects($this->once())
      ->method('getRouteByName')
      ->with('hdbt_cookie_banner.cookie_settings_page')
      ->willThrowException(new RouteNotFoundException());

    // Test that NULL is returned.
    $url = $this->cookieSettings->getCookieSettingsPageUrl();
    $this->assertNull($url);
  }

}
