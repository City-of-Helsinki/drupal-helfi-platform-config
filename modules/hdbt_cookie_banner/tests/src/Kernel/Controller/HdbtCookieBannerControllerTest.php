<?php

declare(strict_types=1);

namespace Drupal\Tests\hdbt_cookie_banner\Kernel\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\KernelTests\KernelTestBase;
use Drupal\hdbt_cookie_banner\Controller\HdbtCookieBannerController;
use Drupal\hdbt_cookie_banner\Form\HdbtCookieBannerForm;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests the HdbtCookieBannerController.
 *
 * @group hdbt_cookie_banner
 */
class HdbtCookieBannerControllerTest extends KernelTestBase {

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
  }

  /**
   * Tests the siteSettings() method.
   */
  public function testSiteSettings() {
    // Define mock configuration values for site settings.
    $mock_site_settings = [
      'site_settings' => '1',
    ];

    // Set up the configuration with mock site settings.
    $config_factory = $this->container->get('config.factory');
    $config_factory->getEditable(HdbtCookieBannerForm::SETTINGS)
      ->set('site_settings', Json::encode($mock_site_settings))
      ->save();

    // Create an instance of the controller with the mocked configuration.
    $controller = new HdbtCookieBannerController($config_factory);

    // Call the siteSettings() method to get the response.
    $response = $controller->siteSettings();

    // Assert that the response is an instance of CacheableJsonResponse.
    $this->assertInstanceOf(CacheableJsonResponse::class, $response);

    // Assert the status code is 200 OK.
    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

    // Assert that the response contains the correct JSON-decoded values.
    $decoded_response = Json::decode($response->getContent());
    $this->assertEquals($mock_site_settings, $decoded_response);

    // Assert that cache max age is set to 600 seconds.
    $this->assertEquals(600, $response->getMaxAge());

    // Assert that cacheable dependencies are present.
    $cache_dependencies = $response->getCacheableMetadata()->getCacheTags();
    $this->assertNotEmpty($cache_dependencies);
    $this->assertContains('config:hdbt_cookie_banner.settings', $cache_dependencies);
  }

}
