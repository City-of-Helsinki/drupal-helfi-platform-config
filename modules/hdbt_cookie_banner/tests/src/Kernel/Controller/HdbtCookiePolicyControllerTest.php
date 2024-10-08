<?php

declare(strict_types=1);

namespace Drupal\Tests\hdbt_cookie_banner\Kernel\Controller;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Tests\hdbt_cookie_banner\Kernel\KernelTestBase;
use Drupal\hdbt_cookie_banner\Controller\HdbtCookiePolicyController;
use Drupal\helfi_api_base\Environment\Address;
use Drupal\helfi_api_base\Environment\Environment;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests the HdbtCookiePolicyController.
 *
 * @coversDefaultClass \Drupal\hdbt_cookie_banner\Controller\HdbtCookiePolicyController
 * @group hdbt_cookie_banner
 */
class HdbtCookiePolicyControllerTest extends KernelTestBase {

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
   * Cookie policy controller.
   *
   * @var \Drupal\hdbt_cookie_banner\Controller\HdbtCookiePolicyController
   */
  protected HdbtCookiePolicyController $controller;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Mock the environment resolver to return a specific environment.
    $mockEnvironment = new Environment(
      new Address('www.test.hel.ninja'),
      new Address('internal-address.local', 'http', 8080),
      ['en' => '/en'],
      EnvironmentEnum::Test,
      [],
    );

    // Mock the EnvironmentResolver service.
    $this->environmentResolver = $this->createMock(EnvironmentResolverInterface::class);
    $this->environmentResolver->method('getEnvironment')->willReturn($mockEnvironment);

    // Create a mock for the LanguageManagerInterface.
    $this->languageManager = $this->createMock(LanguageManagerInterface::class);

    // Set up the container with the mocked services.
    $this->container->set('helfi_api_base.environment_resolver', $this->environmentResolver);
    $this->container->set('language_manager', $this->languageManager);

    // Set up the controller with injected services.
    $this->controller = HdbtCookiePolicyController::create($this->container);
  }

  /**
   * Tests the controller returning cookie content.
   */
  public function testContentWithCustomSettings() {
    // Set up configuration to use custom settings.
    $this->config('hdbt_cookie_banner.settings')
      ->set('use_custom_settings', TRUE)
      ->set('cookie_information.title', 'Cookie policy title')
      ->set('cookie_information.content', 'Cookie policy content')
      ->save();

    // Call the content method of the controller.
    $result = $this->controller->content();

    // Assert that the result is a render array with the expected content.
    $this->assertIsArray($result);
    $this->assertEquals('cookie_policy', $result['#theme']);
    $this->assertEquals('Cookie policy title', $result['#title']);
    $this->assertEquals('Cookie policy content', $result['#content']);
  }

  /**
   * Tests the controller returning a redirect response.
   */
  public function testRedirectToCookiePolicyUrl() {
    // Set up configuration to not use custom settings.
    $this->config('hdbt_cookie_banner.settings')
      ->set('use_custom_settings', FALSE)
      ->save();

    // Create a mock language object to return from getCurrentLanguage.
    $language = $this->createMock(LanguageInterface::class);
    $language->method('getId')->willReturn('en');

    // Configure the mock to return the language
    // when getCurrentLanguage is called.
    $this->languageManager->expects($this->once())
      ->method('getCurrentLanguage')
      ->with(LanguageInterface::TYPE_CONTENT)
      ->willReturn($language);

    // Call the content method of the controller.
    $result = $this->controller->content();

    // Assert that the returned result is a TrustedRedirectResponse.
    $this->assertInstanceOf(TrustedRedirectResponse::class, $result);
    $this->assertEquals('https://www.test.hel.ninja/en/cookie-policy', $result->getTargetUrl());
  }

}
