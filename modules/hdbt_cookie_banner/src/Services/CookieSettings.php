<?php

declare(strict_types=1);

namespace Drupal\hdbt_cookie_banner\Services;

use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Url;
use Drupal\hdbt_cookie_banner\Form\HdbtCookieBannerForm;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Defines CookieSettings service class.
 */
class CookieSettings {

  public function __construct(
    private readonly RouteProviderInterface $routeProvider,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LanguageManagerInterface $languageManager,
    private readonly EnvironmentResolverInterface $environmentResolver,
    private readonly UrlGeneratorInterface $urlGenerator,
    private readonly LibraryDiscoveryInterface $libraryDiscovery,
  ) {
  }

  /**
   * Returns the URL of the cookie settings page.
   *
   * @return \Drupal\Core\Url|null
   *   The URL of the cookie settings page.
   */
  public function getCookieSettingsPageUrl(): ?Url {
    $route_name = 'hdbt_cookie_banner.cookie_settings_page';
    try {
      // Check if the cookie settings page route exists.
      $this->routeProvider->getRouteByName($route_name);

      // Return the cookie settings page URL.
      return Url::fromRoute($route_name);
    }
    catch (RouteNotFoundException) {
    }
    return NULL;
  }

  /**
   * Get the cookie banner API URL.
   *
   * @return string
   *   Cookie banner API URL as a string.
   */
  public function getCookieBannerApiUrl(): string {
    $config = $this->configFactory->get(HdbtCookieBannerForm::SETTINGS);
    $language = $this->languageManager->getDefaultLanguage();

    // Default to Etusivu API URL.
    if (!$config->get('use_custom_settings')) {
      try {
        $environment = $this->environmentResolver->getEnvironment(
          Project::ETUSIVU,
          $this->environmentResolver->getActiveEnvironmentName()
        );

        return vsprintf("%s/api/cookie-banner", [
          $environment->getUrl($language->getId()),
        ]);
      }
      catch (\InvalidArgumentException) {
      }
    }
    return $this->urlGenerator->generateFromRoute(
      'hdbt_cookie_banner.site_settings',
      options: ['language' => $language],
    );
  }

  /**
   * Inject the Cookie banner JavaScript based on the existence of the library.
   *
   * @param array $attachments
   *   Page attachments array.
   * @param string|null $library
   *   A URL / path to manually set JavaScript library.
   */
  public function injectBannerJavaScript(array &$attachments, ?string $library = NULL): void {

    // Load HDS cookie consent JavaScript file from Etusivu instance.
    if (!$library) {
      // Get active Etusivu environment.
      try {
        $environment = $this->environmentResolver->getEnvironment(
          Project::ETUSIVU,
          $this->environmentResolver->getActiveEnvironmentName()
        );
      }
      catch (\InvalidArgumentException) {
        $environment = $this->environmentResolver->getEnvironment(
          Project::ETUSIVU,
          EnvironmentEnum::Prod->value
        );
      }

      // Construct the URL to the HDS cookie consent JS file.
      $library = vsprintf("%s/etusivu-assets/%s/assets/js/hds-cookie-consent.min.js%s", [
        $environment->getBaseUrl(),
        'modules/contrib/helfi_platform_config/modules/hdbt_cookie_banner',
        $this->getCookieLibraryVersion(),
      ]);
    }

    // Attach the HDS cookie consent JS file to HTML head.
    $attachments['#attached']['html_head'][] = [
      [
        '#tag' => 'script',
        '#attributes' => [
          'src' => $library,
          'type' => 'text/javascript',
        ],
      ],
      'external_script',
    ];
  }

  /**
   * Get the version of the current cookie consent library.
   *
   * As the library is injected into the HTML head manually, it's a good
   * practice to include the version of the library in the URL as an argument.
   * The library version is retrieved from the library registry as it should be
   * the same version as in the etusivu environment.
   *
   * @return string
   *   The library version as a URL argument or an empty string.
   */
  protected function getCookieLibraryVersion(): string {
    $library_info = $this->libraryDiscovery->getLibraryByName(
      'hdbt_cookie_banner',
      'hds_cookie_consent',
    );

    if (isset($library_info['version'])) {
      return "?v={$library_info['version']}";
    }
    return '';
  }

}
