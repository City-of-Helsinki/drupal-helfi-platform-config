<?php

declare(strict_types=1);

namespace Drupal\Tests\hdbt_cookie_banner\Kernel\Controller;

use Drupal\Tests\hdbt_cookie_banner\Kernel\KernelTestBase;
use Drupal\hdbt_cookie_banner\Controller\HdbtCookiePolicyController;

/**
 * Tests the HdbtCookiePolicyController.
 *
 * @coversDefaultClass \Drupal\hdbt_cookie_banner\Controller\HdbtCookiePolicyController
 * @group hdbt_cookie_banner
 */
class HdbtCookiePolicyControllerTest extends KernelTestBase {

  /**
   * Tests the content() method of HdbtCookiePolicyController.
   */
  public function testContent() {
    // Create a mock config object.
    $config_values = [
      'cookie_information' => [
        'title' => 'Test Cookie Policy Title',
        'content' => 'This is the cookie policy content.',
      ],
    ];

    // Set up configuration with mock values.
    $config_factory = $this->container->get('config.factory');
    $config_factory->getEditable('hdbt_cookie_banner.settings')
      ->setData($config_values)
      ->save();

    // Create an instance of the controller.
    $controller = new HdbtCookiePolicyController($config_factory);

    // Get the content array.
    $content = $controller->content();

    // Assert that the content has the correct theme and values.
    $this->assertEquals('cookie_policy', $content['#theme']);
    $this->assertEquals('Test Cookie Policy Title', $content['#title']);
    $this->assertEquals('This is the cookie policy content.', $content['#content']);
  }

}
