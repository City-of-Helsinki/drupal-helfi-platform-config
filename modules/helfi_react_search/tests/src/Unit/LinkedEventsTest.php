<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_react_search\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\MemoryBackend;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\helfi_api_base\Features\FeatureManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\helfi_api_base\ApiClient\ApiClient;
use Drupal\helfi_api_base\ApiClient\ApiResponse;
use Drupal\helfi_api_base\Environment\EnvironmentResolver;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_react_search\LinkedEvents;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Log\LoggerInterface;

/**
 * @coversDefaultClass \Drupal\helfi_react_search\LinkedEvents
 * @group helfi_react_search
 */
class LinkedEventsTest extends UnitTestCase {

  use ApiTestTrait;
  use ProphecyTrait;

  /**
   * The cache.
   *
   * @var null|\Drupal\Core\Cache\CacheBackendInterface
   */
  private ?CacheBackendInterface $cache;

  /**
   * The default environment resolver config.
   *
   * @var array
   */
  private array $environmentResolverConfiguration = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    // Create a mock container.
    $container = new ContainerBuilder();

    // Register the TimeInterface service.
    $time = $this->createMock(TimeInterface::class);
    $container->set('datetime.time', $time);

    // MemoryBackend needs a time object, so create one.
    $this->cache = new MemoryBackend($time);
    $this->environmentResolverConfiguration = [
      EnvironmentResolver::PROJECT_NAME_KEY => Project::ASUMINEN,
      EnvironmentResolver::ENVIRONMENT_NAME_KEY => 'local',
    ];

    // Register services what LinkedEvents uses.
    $urlAssembler = $this->createMock('Drupal\Core\Utility\UnroutedUrlAssemblerInterface');
    $urlAssembler->expects($this->any())
      ->method('assemble')
      ->willReturnCallback(function ($url, $arguments) {
        // Mock behavior of the unrouted_url_assembler service.
        return isset($arguments['query'])
          ? $url . '?' . UrlHelper::buildQuery($arguments['query'])
          : $url;
      });

    $container->set('unrouted_url_assembler', $urlAssembler);

    // Set the container to the static \Drupal class.
    \Drupal::setContainer($container);
  }

  /**
   * Create a new time mock object.
   *
   * @param int $expectedTime
   *   The expected time.
   *
   * @return \Prophecy\Prophecy\ObjectProphecy
   *   The mock.
   */
  private function getTimeMock(int $expectedTime) : ObjectProphecy {
    $time = $this->prophesize(TimeInterface::class);
    $time->getRequestTime()->willReturn($expectedTime);
    return $time;
  }

  /**
   * Create a new api client mock object.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The http client.
   * @param \Drupal\Component\Datetime\TimeInterface|null $time
   *   The time prophecy.
   * @param \Drupal\helfi_api_base\Environment\EnvironmentResolverInterface|null $environmentResolver
   *   The environment resolver.
   *
   * @return \Drupal\helfi_api_base\ApiClient\ApiClient
   *   The api client.
   */
  private function getApiClientMock(
    ClientInterface $httpClient,
    ?TimeInterface $time = NULL,
    ?EnvironmentResolverInterface $environmentResolver = NULL,
  ): ApiClient {
    if (!$time) {
      $time = $this->getTimeMock(time())->reveal();
    }

    if (!$environmentResolver) {
      $environmentResolver = new EnvironmentResolver($this->getConfigFactoryStub([
        'helfi_api_base.environment_resolver.settings' => $this->environmentResolverConfiguration,
      ]));
    }

    $logger = $this->prophesize(LoggerInterface::class)->reveal();

    return new ApiClient(
      $httpClient,
      $this->cache,
      $time,
      $environmentResolver,
      $logger,
    );
  }

  /**
   * Constructs a new api manager instance.
   *
   * @param \Drupal\helfi_api_base\ApiClient\ApiClient $client
   *   The http client.
   * @param \Drupal\Core\Language\LanguageManagerInterface|null $languageManager
   *   The language manager.
   * @param \Psr\Log\LoggerInterface|null $logger
   *   The logger channel.
   * @param bool $useFixtures
   *   The fixture feature is enabled if enabled.
   *
   * @return \Drupal\helfi_react_search\LinkedEvents
   *   The api manager instance.
   */
  private function getSut(
    ApiClient $client,
    ?LanguageManagerInterface $languageManager = NULL,
    ?LoggerInterface $logger = NULL,
    bool $useFixtures = FALSE,
  ) : LinkedEvents {

    if (!$languageManager) {
      $languageManager = $this->createMock(LanguageManagerInterface::class);
    }

    if (!$logger) {
      $logger = $this->prophesize(LoggerInterface::class)->reveal();
    }

    $featureManager = $this->prophesize(FeatureManagerInterface::class);
    $featureManager
      ->isEnabled(FeatureManagerInterface::USE_MOCK_RESPONSES)
      ->willReturn($useFixtures);

    return new LinkedEvents(
      $client,
      $languageManager,
      $logger,
      $featureManager->reveal(),
    );
  }

  /**
   * Tests Linked Events API responses.
   *
   * @covers ::__construct
   * @covers ::get
   * @covers ::formatPlacesUrl
   * @covers ::getFixture
   * @covers ::getFixturePath
   */
  public function testGetLinkedEvents() : void {
    $requests = [];
    $httpClient = $this->createMockHistoryMiddlewareHttpClient($requests, [
      new Response(200, body: json_encode(['key' => 'value'])),
    ]);
    $languageManager = $this->createMock(LanguageManagerInterface::class);
    $sut = $this->getSut(
      $this->getApiClientMock($httpClient), $languageManager
    );

    // Build event url.
    $event_url = 'https://' . $sut::FIXTURE_NAME . '?places=tprek:1923';

    // Test the API response.
    $response = $sut->get($event_url);
    $this->assertInstanceOf(ApiResponse::class, $response);

    // Make sure cache is used (request queue should be empty).
    $sut->get($event_url);
  }

  /**
   * Tests Linked Events API responses.
   *
   * @covers ::__construct
   * @covers ::getPlacesList
   * @covers ::parseResponse
   */
  public function testGetPlacesList() : void {
    $requests = [];
    $httpClient = $this->createMockHistoryMiddlewareHttpClient($requests, [
      new Response(200, body: json_encode(['key' => 'value'])),
    ]);
    $languageManager = $this->createMock(LanguageManagerInterface::class);
    $sut = $this->getSut(
      $this->getApiClientMock($httpClient), $languageManager,
      useFixtures: TRUE,
    );

    // Build event url.
    $event_url = 'https://' . $sut::FIXTURE_NAME . '?places=tprek:1923';

    // Test that the getPlacesList method returns the fixture.
    $response = $sut->getPlacesList($event_url);
    $this->assertIsArray($response);
    $this->assertIsObject($response['prefix:1']);
    $this->assertObjectHasProperty('id', $response['prefix:1']);
  }

  /**
   * Tests Linked Events fixtures.
   *
   * @covers ::get
   * @covers ::formatPlacesUrl
   * @covers ::parseParams
   * @covers ::getFixturePath
   * @covers ::getFixture
   */
  public function testGetLinkedEventsFixture() : void {
    $requests = [];

    $path_to_fixture = __DIR__ . '/../../../fixtures/' . LinkedEvents::FIXTURE_NAME . '.json';
    $fixture = file_get_contents($path_to_fixture);
    $httpClient = $this->createMockHistoryMiddlewareHttpClient($requests, [
      new Response(200, body: $fixture),
    ]);

    $languageManager = $this->createMock(LanguageManagerInterface::class);
    $sut = $this->getSut(
      $this->getApiClientMock($httpClient), $languageManager
    );

    // Construct the linked events URL.
    $event_url = 'https://' . $sut::FIXTURE_NAME;

    // Force fixture to be used by setting the URL field to the fixture path.
    $sut->parseParams($event_url);

    // Assert the API response.
    $response = $sut->get($event_url);
    $this->assertJsonStringEqualsJsonString($fixture, json_encode($response->data));
  }

  /**
   * Tests Linked Events request.
   *
   * @covers ::__construct
   * @covers ::getEventsRequest
   * @covers ::formatPlacesUrl
   * @covers ::parseParams
   */
  public function testGetEventsRequest() : void {
    $requests = [];
    $httpClient = $this->createMockHistoryMiddlewareHttpClient($requests, [
      new Response(200, body: json_encode(['key' => 'value'])),
    ]);
    $languageManager = $this->createMock(LanguageManagerInterface::class);
    $languageManager->expects($this->any())
      ->method('getCurrentLanguage')
      ->willReturn(new Language(['id' => 'en']));

    $client = $this->getApiClientMock($httpClient);
    $sut = $this->getSut(
      $client,
      $languageManager
    );

    // Test the API response with no options set.
    $url_no_options = $sut->getEventsRequest();
    $this->assertEquals('https://api.hel.fi/linkedevents/v1/event/?event_type=General&format=json&include=keywords%2Clocation&page=1&page_size=3&sort=end_time&start=now&super_event_type=umbrella%2Cnone&language=en&all_ongoing=true', $url_no_options);

    // Test the API response with options set.
    $url_with_options = $sut->getEventsRequest(['keyword' => 'matko:museo,yso:p4934', 'places' => 'tprek:1923'], '4');
    $this->assertEquals('https://api.hel.fi/linkedevents/v1/event/?event_type=General&format=json&include=keywords%2Clocation&page=1&page_size=4&sort=end_time&start=now&super_event_type=umbrella%2Cnone&language=en&keyword=matko%3Amuseo%2Cyso%3Ap4934&places=tprek%3A1923&all_ongoing=true', $url_with_options);

    // Test the API response with forced fixture.
    $url_with_fixture = $this
      ->getSut($client, $languageManager, useFixtures: TRUE)
      ->getEventsRequest();
    $this->assertStringContainsString($sut::FIXTURE_NAME, $url_with_fixture);

    // Test the formatPlacesUrl method.
    $url_format_places = $sut->formatPlacesUrl('tprek:1923');
    $this->assertEquals('https://api.hel.fi/linkedevents/v1/place?has_upcoming_events=true&sort=name&page_size=100', $url_format_places);

    // Test the parseParams method; add keywords from category selections as
    // well as manually.
    $params = $sut->parseParams('https://tapahtumat.hel.fi/fi/haku?categories=movie&keyword=yso:p6357');
    $this->assertEquals([
      'keyword' => 'yso:p1235,yso:p6357',
    ], $params);

    // Test the parseParams method with multiple params.
    $params = $sut->parseParams('https://tapahtumat.hel.fi/fi/haku?text=test_text&categories=movie&start=2025-01-31&divisions=test_division&places=test_place&dateTypes=today&isFree=true&onlyEveningEvents=true&onlyRemoteEvents=true&onlyChildrenEvents=true');
    $this->assertEquals([
      'all_ongoing_AND' => 'test_text',
      'keyword' => 'yso:p1235',
      'start' => 'now',
      'division' => 'test_division',
      'location' => 'test_place',
      'end' => 'today',
      'is_free' => 'true',
      'keyword_AND' => 'yso:p4354',
      'starts_after' => '16',
      'internet_based' => 'true',
    ], $params);

    // Test the parseParams method; params without special handling should pass
    // through untouched.
    $params = $sut->parseParams('https://tapahtumat.hel.fi/fi/haku?test_param_1=test_value_1&test_param_2=test_value_2');
    $this->assertEquals([
      'test_param_1' => 'test_value_1',
      'test_param_2' => 'test_value_2',
    ], $params);
  }

  /**
   * Make sure cache can be bypassed when configured so.
   *
   * @covers ::__construct
   * @covers ::get
   * @covers ::withBypassCache
   */
  public function testCacheBypass() : void {
    $requests = [];
    $httpClient = $this->createMockHistoryMiddlewareHttpClient($requests, [
      new Response(200, body: json_encode(['value' => 1])),
      new Response(200, body: json_encode(['value' => 2])),
    ]);
    $languageManager = $this->createMock(LanguageManagerInterface::class);
    $sut = $this->getSut(
      $this->getApiClientMock($httpClient), $languageManager
    );
    $event_url = 'https://' . $sut::FIXTURE_NAME;

    // Make sure cache is used for all requests.
    for ($i = 0; $i < 3; $i++) {
      $response = $sut->get($event_url);
      $this->assertInstanceOf(\stdClass::class, $response->data);
      $this->assertEquals(1, $response->data->value);
    }

    // Make sure cache is bypassed when configured so and the cached content
    // is updated.
    $response = $sut->withBypassCache()->get($event_url);
    $this->assertInstanceOf(\stdClass::class, $response->data);
    $this->assertEquals(2, $response->data->value);

    // withBypassCache() method creates a clone of LinkedEvents instance to
    // ensure cache is only bypassed when explicitly told so.
    // We defined only two responses, so this should fail to OutOfBoundException
    // if cache was bypassed here.
    for ($i = 0; $i < 3; $i++) {
      $response = $sut->get($event_url);
      $this->assertInstanceOf(\stdClass::class, $response->data);
      $this->assertEquals(2, $response->data->value);
    }
  }

}
