<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel;

use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests Calculator settings page.
 *
 * @group helfi_calculator
 */
class CalculatorSettingsTest extends KernelTestBase {

  use UserCreationTrait;
  use ApiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'language',
    'system',
    'helfi_calculator',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    // Create user so we don't accidentally get a user with uid 1
    // permissions.
    $this->createUser();
  }

  /**
   * Tests settings page route permissions.
   */
  public function testPermissions() : void {
    $route = Url::fromRoute('helfi_calculator.calculator_settings_form');
    $request = $this->getMockedRequest($route->toString());
    $response = $this->processRequest($request);

    $this->assertEquals(403, $response->getStatusCode());

    $user = $this->createUser(['access administration pages']);
    $this->setCurrentUser($user);

    $response = $this->processRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
  }

}
