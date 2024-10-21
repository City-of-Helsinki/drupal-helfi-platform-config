<?php

declare(strict_types=1);

namespace Drupal\hdbt_cookie_banner\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\hdbt_cookie_banner\Form\HdbtCookieBannerForm;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines HdbtCookieSettingsPageController class.
 */
final class HdbtCookieSettingsPageController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Constructs a new instance.
   */
  public function __construct(
    protected readonly EnvironmentResolverInterface $environmentResolver,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('helfi_api_base.environment_resolver')
    );
  }

  /**
   * Display the cookie information.
   *
   * @todo UHF-8650: Check if this cookie settings route is still needed.
   * EU Cookie compliance module used to have a separate page for the cookie
   * settings. This controller retains the same functionality.
   * Assess the necessity of this feature once the HDS cookie banner is in use.
   *
   * @return \Drupal\Core\Routing\TrustedRedirectResponse|array
   *   Return redirect response or render array.
   */
  public function content(): TrustedRedirectResponse | array {
    $config = $this->config(HdbtCookieBannerForm::SETTINGS);
    $content = [];

    // If custom settings are used, return the cookie settings content.
    if ($config->get('use_custom_settings')) {
      // Get the cookie settings content.
      $content['#theme'] = 'cookie_settings_page';
      $content['#title'] = $config->get('cookie_information.title');
      $content['#content'] = $config->get('cookie_information.content');
      return $content;
    }

    // Otherwise return a redirect to Etusivu project cookie setting page URL.
    try {
      $environment = $this
        ->environmentResolver
        ->getEnvironment(Project::ETUSIVU, $this->environmentResolver->getActiveEnvironmentName());
    }
    catch (\InvalidArgumentException) {
      $environment = $this
        ->environmentResolver
        ->getEnvironment(Project::ETUSIVU, EnvironmentEnum::Prod->value);
    }

    $language = $this->languageManager()->getCurrentLanguage(LanguageInterface::TYPE_CONTENT);

    $cookiePolicyUrl = vsprintf("%s%s/%s", [
      $environment->getBaseUrl(),
      $environment->getPath($language->getId()),
      'cookie-settings',
    ]);

    return new TrustedRedirectResponse($cookiePolicyUrl);
  }

}
