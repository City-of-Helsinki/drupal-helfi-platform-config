<?php

declare(strict_types=1);

namespace Drupal\Tests\hdbt_cookie_banner\Kernel;

use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\KernelTests\KernelTestBase as CoreKernelTestBase;
use Drupal\hdbt_cookie_banner\Controller\HdbtCookieSettingsPageController;
use Drupal\hdbt_cookie_banner\Form\HdbtCookieBannerForm;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Kernel test base for news feed list tests.
 */
class KernelTestBase extends CoreKernelTestBase {

  use EnvironmentResolverTrait;

  /**
   * Environment resolver.
   *
   * @var \Drupal\helfi_api_base\Environment\EnvironmentResolverInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected EnvironmentResolverInterface|MockObject $environmentResolver;

  /**
   * Language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected LanguageManagerInterface|MockObject $languageManager;

  /**
   * The mock language manager.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected ConfigFactoryInterface|MockObject $configFactory;

  /**
   * The mock route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected RouteProviderInterface|MockObject $routeProvider;

  /**
   * The mock module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList|\PHPUnit\Framework\MockObject\MockObject
   */
  protected ModuleExtensionList|MockObject $moduleExtensionList;

  /**
   * Cookie settings page controller.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected UrlGeneratorInterface|MockObject $urlGenerator;

  /**
   * The mock library discovery.
   *
   * @var \Drupal\Core\Asset\LibraryDiscoveryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected LibraryDiscoveryInterface|MockObject $libraryDiscovery;

  /**
   * Cookie settings page controller.
   *
   * @var \Drupal\hdbt_cookie_banner\Controller\HdbtCookieSettingsPageController
   */
  protected HdbtCookieSettingsPageController $controller;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'hdbt_cookie_banner',
    'helfi_api_base',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'hdbt_cookie_banner']);

    $this->setActiveProject(Project::ASUMINEN, EnvironmentEnum::Test);

    // Create a mocks for the needed interfaces.
    $this->routeProvider = $this->createMock(RouteProviderInterface::class);
    $this->languageManager = $this->createMock(LanguageManagerInterface::class);
    $this->moduleExtensionList = $this->createMock(ModuleExtensionList::class);
    $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
    $this->libraryDiscovery = $this->createMock(LibraryDiscoveryInterface::class);

    // Set up the container with the mocked services.
    $this->container->set('router.route_provider', $this->routeProvider);
    $this->container->set('language_manager', $this->languageManager);
    $this->container->set('extension.list.module', $this->moduleExtensionList);
    $this->container->set('url_generator', $this->urlGenerator);
    $this->container->set('library.discovery', $this->libraryDiscovery);

    // Set up the controller with injected services.
    $this->controller = HdbtCookieSettingsPageController::create($this->container);
  }

  /**
   * Set up the configurations for testing different settings.
   *
   * @param array $configuration
   *   Configurations as an array.
   */
  protected function setUpTheConfigurations(array $configuration): void {
    // Mock the 'HdbtCookieBannerForm::SETTINGS' configuration.
    $this->config(HdbtCookieBannerForm::SETTINGS)->setData($configuration)->save();
  }

}
