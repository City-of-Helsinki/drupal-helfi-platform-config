<?php

declare(strict_types=1);

namespace Drupal\Test\helfi_react_search\Kernel\Controller;

use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Form\FormState;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * Kernel tests for LinkedEventsAutocompleteController.
 *
 * @see \Drupal\helfi_react_search\Controller\LinkedEventsAutocompleteController
 */
final class AutocompleteControllerTest extends KernelTestBase {

  use ApiTestTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'helfi_api_base',
    'helfi_react_search',
    'helfi_platform_config',
    'serialization',
    'config_rewrite',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
  }

  /**
   * Tests autocomplete.
   */
  public function testAutocomplete(): void {
    $this->setupMockHttpClient([
      // Simulate API failure.
      new RequestException("test failure", new Request('GET', 'test'), new Response(504)),
      // Valid response.
      new Response(body: json_encode([
        // Response from linked events api:
        // https://api.hel.fi/linkedevents/v1/keyword/.
        'data' => [
          [
            'id' => 'test-id',
            'name' => [
              'fi' => 'Testi',
              'en' => 'Test',
            ],
          ],
        ],
      ])),
    ]);

    // User has no permissions.
    $request = $this->getMockedRequest($this->getAutocompleteUrl());
    $this->assertEquals(403, $this->processRequest($request)->getStatusCode());

    // Autocomplete route accepts any logged-in user.
    $this->setUpCurrentUser();

    // Missing query.
    $request = $this->getMockedRequest($this->getAutocompleteUrl(query: NULL));
    $this->assertEquals(400, $this->processRequest($request)->getStatusCode());

    // Bad hmac for settings.
    $request = $this->getMockedRequest($this->getAutocompleteUrl());
    $this->assertEquals(403, $this->processRequest($request)->getStatusCode());

    // Process autocomplete settings.
    // This creates keyvalue entry that the autocomplete route uses.
    $form = [];
    $element = [
      '#autocreate' => FALSE,
      '#target_type' => 'test',
      '#selection_handler' => 'default',
      '#selection_settings' => [
        'key' => 'value',
      ],
    ];
    $element = EntityAutocomplete::processEntityAutocomplete($element, new FormState(), $form);

    // Bad hmac for settings.
    $request = $this->getMockedRequest($this->getAutocompleteUrl(parameters: ['target_type' => 'invalid'] + $element['#autocomplete_route_parameters']));
    $this->assertEquals(403, $this->processRequest($request)->getStatusCode());

    // External api fails.
    $request = $this->getMockedRequest($this->getAutocompleteUrl(parameters: $element['#autocomplete_route_parameters']));
    $this->assertEquals(503, $this->processRequest($request)->getStatusCode());

    // Good HMAC.
    $request = $this->getMockedRequest($this->getAutocompleteUrl(parameters: $element['#autocomplete_route_parameters']));
    $response = $this->processRequest($request);

    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals([
      'results' => [
        [
          'id' => json_encode([
            'id' => 'test-id',
            'name' => [
              'fi' => 'Testi',
              'en' => 'Test',
            ],
          ]),
          'text' => 'Test',
        ],
      ],
    ], json_decode($response->getContent(), associative: TRUE));
  }

  /**
   * Builds autocomplete route URL.
   */
  private function getAutocompleteUrl(
    array $parameters = [
      'target_type' => 'test',
      'selection_settings_key' => '123',
    ],
    ?string $query = 'test',
  ): string {
    $options = [];

    if ($query) {
      $options['query'] = [
        'q' => $query,
      ];
    }

    $url = Url::fromRoute('helfi_react_search.linked_events.autocomplete', $parameters, $options);

    return $url->toString();
  }

}
