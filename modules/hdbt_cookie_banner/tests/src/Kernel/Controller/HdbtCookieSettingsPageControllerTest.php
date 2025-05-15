<?php

declare(strict_types=1);

namespace Drupal\Tests\hdbt_cookie_banner\Kernel\Controller;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Tests\hdbt_cookie_banner\Kernel\KernelTestBase;

/**
 * Tests the HdbtCookieSettingsPageController.
 *
 * @group hdbt_cookie_banner
 */
class HdbtCookieSettingsPageControllerTest extends KernelTestBase {

  /**
   * Tests the controller returning cookie content.
   */
  public function testContentWithCustomSettings() {
    // Set up configuration to not use custom settings.
    $expected = [
      'use_custom_settings' => TRUE,
      'cookie_information' => [
        'title' => 'Cookie settings title',
        'content' => 'Cookie settings content',
      ],
    ];
    $this->setUpTheConfigurations($expected);

    // Call the content method of the controller.
    $result = $this->controller->content();

    // Assert that the result is a render array with the expected content.
    $this->assertIsArray($result);
    $this->assertEquals('cookie_settings_page', $result['#theme']);
    $this->assertEquals('Cookie settings title', $result['#title']);
    $this->assertEquals('Cookie settings content', $result['#content']);
  }

  /**
   * Tests the controller returning a redirect response.
   */
  public function testRedirectToCookiePolicyUrl() {
    // Set up configuration to not use custom settings.
    $expected = [
      'use_custom_settings' => FALSE,
    ];
    $this->setUpTheConfigurations($expected);

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
    $this->assertEquals('https://www.test.hel.ninja/en/cookie-settings', $result->getTargetUrl());
  }

}
