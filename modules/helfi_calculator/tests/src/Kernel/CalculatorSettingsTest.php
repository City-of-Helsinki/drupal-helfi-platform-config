<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\Core\Url;
use Drupal\helfi_calculator\Form\CalculatorSettings;
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

  /**
   * Tests that only valid settings can be saved.
   */
  public function testValidation() : void {
    $this->config('helfi_calculator.settings')
      ->set('calculators', [
        'home_care_client_fee' => [
          'label' => 'Home care client fee',
          'active' => FALSE,
          'json' => '{}',
        ],
      ])
      ->save();

    $submit = function (string $json) : array {
      $formState = (new FormState())->setValues([
        'calculators' => [
          'home_care_client_fee' => ['active' => 1, 'json' => $json],
        ],
      ]);
      $this->container->get('form_builder')->submitForm(CalculatorSettings::class, $formState);
      return $formState->getErrors();
    };

    $this->assertArrayHasKey('calculators][home_care_client_fee][json', $submit('{"guardianship_fee": '));
    $this->assertArrayHasKey('calculators][home_care_client_fee][json', $submit('{"<img src=x onerror=alert(1)>": 1}'));
    $this->assertSame('{}', $this->config('helfi_calculator.settings')->get('calculators.home_care_client_fee.json'));

    // Text is allowed. It's filtered when the settings are output.
    $this->assertSame([], $submit('{"guardianship_fee": 37.5, "title": "Asumismaksu"}'));
    $this->assertSame('{"guardianship_fee": 37.5, "title": "Asumismaksu"}', $this->config('helfi_calculator.settings')->get('calculators.home_care_client_fee.json'));
  }

}
