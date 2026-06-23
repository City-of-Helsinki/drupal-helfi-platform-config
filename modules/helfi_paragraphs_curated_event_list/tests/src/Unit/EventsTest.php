<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_curated_event_list\Unit;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Utility\Token;
use Drupal\external_entities\Entity\ExternalEntityInterface;
use Drupal\helfi_paragraphs_curated_event_list\Plugin\ExternalEntities\StorageClient\Events;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Unit tests for LinkedEvents external entity storage client.
 */
#[Group('helfi_paragraphs_curated_event_list')]
class EventsTest extends UnitTestCase {

  /**
   * Plugin ID under test.
   */
  private const string PLUGIN_ID = 'linkedevents_events';

  /**
   * Minimal plugin definition for construction.
   *
   * @var array<string, string>
   */
  private const array PLUGIN_DEFINITION = [
    'label' => 'LinkedEvents: Events',
  ];

  /**
   * Builds the storage client with mocked HTTP and language services.
   */
  private function createStorageClient(
    ClientInterface $httpClient,
    LanguageManagerInterface $languageManager,
  ): Events {
    $events = new Events(
      [],
      self::PLUGIN_ID,
      self::PLUGIN_DEFINITION,
      $this->getStringTranslationStub(),
      $this->createLoggerFactoryMock(),
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(EntityFieldManagerInterface::class),
      $this->createTokenMock(),
    );

    $reflection = new \ReflectionClass($events);
    $languageProperty = $reflection->getProperty('languageManager');
    $languageProperty->setValue($events, $languageManager);

    $clientProperty = $reflection->getProperty('client');
    $clientProperty->setValue($events, $httpClient);

    return $events;
  }

  /**
   * Creates a language manager mock that reports the given content langcode.
   */
  private function createLanguageManager(string $langcode): LanguageManagerInterface {
    $language = $this->createMock(LanguageInterface::class);
    $language->method('getId')->willReturn($langcode);

    $languageManager = $this->createMock(LanguageManagerInterface::class);
    $languageManager->method('getCurrentLanguage')
      ->with(LanguageInterface::TYPE_CONTENT)
      ->willReturn($language);

    return $languageManager;
  }

  /**
   * Returns a JSON response for LinkedEvents-style payloads.
   *
   * @param array<mixed> $payload
   *   Decoded JSON structure (e.g. ['data' => []]).
   */
  private function jsonResponse(array $payload): Response {
    return new Response(200, [], (string) json_encode($payload));
  }

  /**
   * Creates an HTTP client that records the request URI and returns empty data.
   */
  private function createHttpClientCapturingRequestUri(RequestUriCapture $capture): ClientInterface {
    $capture->uri = NULL;
    $client = $this->createMock(ClientInterface::class);
    $client->expects($this->once())
      ->method('request')
      ->willReturnCallback(function (string $method, string $uri) use ($capture) {
        $capture->uri = $uri;
        return $this->jsonResponse(['data' => []]);
      });
    return $client;
  }

  /**
   * Creates a logger factory mock suitable for StorageClientBase.
   */
  private function createLoggerFactoryMock(): LoggerChannelFactoryInterface {
    $logger = $this->createMock(LoggerChannelInterface::class);
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($logger);
    return $loggerFactory;
  }

  /**
   * Creates a Token service mock without invoking the real constructor.
   */
  private function createTokenMock(): Token {
    return $this->getMockBuilder(Token::class)
      ->disableOriginalConstructor()
      ->getMock();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    \Drupal::unsetContainer();
    parent::tearDown();
  }

  /**
   * Tests that create() wires services from the container.
   */
  #[Test]
  public function testCreateWiresContainerServices(): void {
    $httpClient = $this->createMock(ClientInterface::class);
    $languageManager = $this->createLanguageManager('fi');

    $services = [
      'string_translation' => $this->getStringTranslationStub(),
      'logger.factory' => $this->createLoggerFactoryMock(),
      'entity_type.manager' => $this->createMock(EntityTypeManagerInterface::class),
      'entity_field.manager' => $this->createMock(EntityFieldManagerInterface::class),
      'token' => $this->createTokenMock(),
      'language_manager' => $languageManager,
      'http_client' => $httpClient,
    ];

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
      ->willReturnCallback(static function (string $id) use ($services) {
        return $services[$id];
      });

    $plugin = Events::create(
      $container,
      [],
      self::PLUGIN_ID,
      self::PLUGIN_DEFINITION,
    );

    $this->assertInstanceOf(Events::class, $plugin);
  }

  /**
   * Tests that save() returns a successful status code.
   */
  #[Test]
  public function testSaveReturnsOne(): void {
    $storage = $this->createStorageClient(
      $this->createMock(ClientInterface::class),
      $this->createLanguageManager('en'),
    );

    $entity = $this->createMock(ExternalEntityInterface::class);
    $this->assertSame(1, $storage->save($entity));
  }

  /**
   * Tests that delete() completes without error.
   */
  #[Test]
  public function testDeleteDoesNotThrow(): void {
    $this->expectNotToPerformAssertions();

    $storage = $this->createStorageClient(
      $this->createMock(ClientInterface::class),
      $this->createLanguageManager('en'),
    );

    $entity = $this->createMock(ExternalEntityInterface::class);
    $storage->delete($entity);
  }

  /**
   * Tests that loadMultiple() queries the event API with stripped compound IDs.
   */
  #[Test]
  public function testLoadMultiplePassesIdsToQuery(): void {
    $capture = new RequestUriCapture();
    $httpClient = $this->createHttpClientCapturingRequestUri($capture);

    $storage = $this->createStorageClient(
      $httpClient,
      $this->createLanguageManager('fi'),
    );

    $storage->loadMultiple(['helsinki:abc,fi', 'helsinki:def,fi']);

    $this->assertIsString($capture->uri);
    $this->assertStringContainsString('/event?', $capture->uri);
    $this->assertStringContainsString(
      'ids=helsinki%3Aabc%2Chelsinki%3Adef',
      $capture->uri,
    );
    $this->assertStringContainsString('language=fi', $capture->uri);
    $this->assertStringContainsString('event_type=General%2CCourse', $capture->uri);
  }

  /**
   * Data provider: query cases that should yield an empty result set.
   *
   * @return \Generator
   *   Scenario label keyed rows; each row holds an HTTP client factory.
   */
  public static function queryReturnsEmptyClientFactoryProvider(): \Generator {
    yield 'http exception' => [
      static function (self $test): ClientInterface {
        $client = $test->createMock(ClientInterface::class);
        $client->method('request')
          ->willThrowException(new RequestException('Error', new Request('GET', 'test')));
        return $client;
      },
    ];

    yield 'missing data key' => [
      static function (self $test): ClientInterface {
        $client = $test->createMock(ClientInterface::class);
        $client->method('request')
          ->willReturn($test->jsonResponse([]));
        return $client;
      },
    ];
  }

  /**
   * Tests that query() returns [] for failing or empty API responses.
   */
  #[Test]
  #[DataProvider('queryReturnsEmptyClientFactoryProvider')]
  public function testQueryReturnsEmpty(\Closure $clientFactory): void {
    $httpClient = $clientFactory($this);
    $storage = $this->createStorageClient(
      $httpClient,
      $this->createLanguageManager('en'),
    );

    $this->assertSame([], $storage->query([['value' => 'concert']]));
  }

  /**
   * Data provider: query parameters and expected URI fragments.
   *
   * @return \Generator
   *   Langcode, parameters for query(), and substrings expected in the URI.
   */
  public static function queryRequestUriCases(): \Generator {
    yield 'search plain text' => [
      'sv',
      [['value' => 'jazz evening']],
      ['/search?', 'input=jazz+evening', 'language=sv', 'type=event'],
    ];

    yield 'id field filter' => [
      'en',
      [
        [
          'field' => 'id',
          'value' => ['helsinki:one', 'helsinki:two'],
        ],
      ],
      ['/event?', 'ids=helsinki%3Aone%2Chelsinki%3Atwo'],
    ];

    yield 'colon-shaped id value' => [
      'en',
      [['value' => 'helsinki:agnjd4b73u']],
      ['/event?', 'ids=helsinki%3Aagnjd4b73u'],
    ];
  }

  /**
   * Tests request URI built for different query parameter shapes.
   *
   * @phpstan-param array<mixed> $parameters
   * @phpstan-param list<string> $uriFragments
   */
  #[Test]
  #[DataProvider('queryRequestUriCases')]
  public function testQueryBuildsExpectedRequestUri(
    string $langcode,
    array $parameters,
    array $uriFragments,
  ): void {
    $capture = new RequestUriCapture();
    $httpClient = $this->createHttpClientCapturingRequestUri($capture);

    $storage = $this->createStorageClient(
      $httpClient,
      $this->createLanguageManager($langcode),
    );

    $storage->query($parameters);

    $this->assertIsString($capture->uri);
    foreach ($uriFragments as $fragment) {
      $this->assertStringContainsString($fragment, $capture->uri);
    }
  }

  /**
   * Data provider: content language, API event row, expected external_link.
   *
   * Covers buildExternalLink() language and type_id defaults and all path
   * combinations for tapahtumat vs harrastukset.
   *
   * @return \Generator
   *   Each row: content language ID, single event from API, expected link.
   */
  public static function externalLinkCases(): \Generator {
    $start = '2030-06-15T10:00:00+00:00';

    yield 'event fi general type' => [
      'fi',
      [
        'id' => 'helsinki:fi-general',
        'type_id' => 'General',
        'start_time' => $start,
        'name' => ['fi' => 'E'],
      ],
      'https://tapahtumat.hel.fi/fi/tapahtumat/helsinki:fi-general',
    ];

    yield 'event sv general type' => [
      'sv',
      [
        'id' => 'helsinki:sv-general',
        'type_id' => 'General',
        'start_time' => $start,
        'name' => ['sv' => 'S'],
      ],
      'https://tapahtumat.hel.fi/sv/kurser/helsinki:sv-general',
    ];

    yield 'event en non course type_id' => [
      'en',
      [
        'id' => 'helsinki:en-workshop',
        'type_id' => 'Workshop',
        'start_time' => $start,
        'name' => ['en' => 'W'],
      ],
      'https://tapahtumat.hel.fi/en/events/helsinki:en-workshop',
    ];

    yield 'event non fi sv content language uses en path' => [
      'de',
      [
        'id' => 'helsinki:de-lang',
        'type_id' => 'General',
        'start_time' => $start,
        'name' => ['de' => 'D'],
      ],
      'https://tapahtumat.hel.fi/en/events/helsinki:de-lang',
    ];

    yield 'event fi missing type_id' => [
      'fi',
      [
        'id' => 'helsinki:no-type-key',
        'start_time' => $start,
        'name' => ['fi' => 'N'],
      ],
      'https://tapahtumat.hel.fi/fi/tapahtumat/helsinki:no-type-key',
    ];

    yield 'event fi null type_id' => [
      'fi',
      [
        'id' => 'helsinki:null-type-id',
        'type_id' => NULL,
        'start_time' => $start,
        'name' => ['fi' => 'T'],
      ],
      'https://tapahtumat.hel.fi/fi/tapahtumat/helsinki:null-type-id',
    ];

    yield 'course fi' => [
      'fi',
      [
        'id' => 'harrastukset:course-fi',
        'type_id' => 'Course',
        'start_time' => $start,
        'name' => ['fi' => 'C'],
      ],
      'https://harrastukset.hel.fi/fi/kurssit/harrastukset:course-fi',
    ];

    yield 'course sv' => [
      'sv',
      [
        'id' => 'harrastukset:course-sv',
        'type_id' => 'Course',
        'start_time' => $start,
        'name' => ['sv' => 'K'],
      ],
      'https://harrastukset.hel.fi/sv/kurser/harrastukset:course-sv',
    ];

    yield 'course en' => [
      'en',
      [
        'id' => 'harrastukset:course-en',
        'type_id' => 'Course',
        'start_time' => $start,
        'name' => ['en' => 'E'],
      ],
      'https://harrastukset.hel.fi/en/courses/harrastukset:course-en',
    ];

    yield 'course non fi sv content language uses en path' => [
      'ru',
      [
        'id' => 'harrastukset:course-ru',
        'type_id' => 'Course',
        'start_time' => $start,
        'name' => ['ru' => 'R'],
      ],
      'https://harrastukset.hel.fi/en/courses/harrastukset:course-ru',
    ];
  }

  /**
   * Tests external_link URLs for buildExternalLink() branches.
   *
   * @phpstan-param array<mixed> $eventRow
   */
  #[Test]
  #[DataProvider('externalLinkCases')]
  public function testQueryBuildsExpectedExternalLink(
    string $contentLangcode,
    array $eventRow,
    string $expectedExternalLink,
  ): void {
    $payload = ['data' => [$eventRow]];
    $httpClient = $this->createMock(ClientInterface::class);
    $httpClient->method('request')
      ->willReturn($this->jsonResponse($payload));

    $storage = $this->createStorageClient(
      $httpClient,
      $this->createLanguageManager($contentLangcode),
    );

    $result = $storage->query([['value' => 'x']]);
    $key = sprintf('%s,%s', $eventRow['id'], $contentLangcode);

    $this->assertArrayHasKey($key, $result);
    $this->assertSame($expectedExternalLink, $result[$key]['external_link']);
  }

  /**
   * Tests skipping events missing a name for the active language.
   */
  #[Test]
  public function testQuerySkipsEventsWithoutTranslationForCurrentLanguage(): void {
    $payload = [
      'data' => [
        [
          'id' => 'helsinki:skip',
          'name' => ['en' => 'English only'],
          'start_time' => '2030-01-15T18:00:00+00:00',
        ],
        [
          'id' => 'helsinki:keep',
          'name' => ['fi' => 'Vain suomeksi'],
          'start_time' => '2030-01-20T10:30:00+00:00',
          'type_id' => 'General',
        ],
      ],
    ];

    $httpClient = $this->createMock(ClientInterface::class);
    $httpClient->method('request')
      ->willReturn($this->jsonResponse($payload));

    $storage = $this->createStorageClient(
      $httpClient,
      $this->createLanguageManager('fi'),
    );

    $result = $storage->query([['value' => 'helsinki:keep']]);

    $this->assertCount(1, $result);
    $this->assertArrayHasKey('helsinki:keep,fi', $result);
    $event = $result['helsinki:keep,fi'];
    $this->assertSame('helsinki:keep,fi', $event['id']);
    $this->assertSame('Vain suomeksi', $event['name']);
    $this->assertMatchesRegularExpression(
      '/^Vain suomeksi \(\d{2}\.\d{2}\.\d{4} \d{2}:\d{2}\)$/',
      $event['title'],
    );
    $this->assertSame(
      'https://tapahtumat.hel.fi/fi/tapahtumat/helsinki:keep',
      $event['external_link'],
    );
  }

  /**
   * Data provider: stub methods that return an empty array.
   *
   * @return \Generator
   *   Each row is a callable (EventsTest, Events) returning the method result.
   */
  public static function emptyArrayStubMethodProvider(): \Generator {
    yield 'querySource' => [
      static fn (EventsTest $test, Events $storage): array => $storage->querySource(),
    ];

    yield 'transliterateDrupalFilters' => [
      static fn (EventsTest $test, Events $storage): array => $storage->transliterateDrupalFilters([]),
    ];

    yield 'buildConfigurationForm' => [
      static fn (EventsTest $test, Events $storage): array => $storage->buildConfigurationForm(
        [],
        $test->createMock(FormStateInterface::class),
      ),
    ];
  }

  /**
   * Tests stub API methods that intentionally return no data.
   */
  #[Test]
  #[DataProvider('emptyArrayStubMethodProvider')]
  public function testStubMethodsReturnEmptyArray(\Closure $invoke): void {
    $storage = $this->createStorageClient(
      $this->createMock(ClientInterface::class),
      $this->createLanguageManager('en'),
    );

    $this->assertSame([], $invoke($this, $storage));
  }

}

/**
 * Holds the last HTTP request URI for URI assertion tests.
 */
final class RequestUriCapture {

  /**
   * The request URI from the last mocked HTTP GET, or NULL before request.
   *
   * @var string|null
   */
  public ?string $uri = NULL;

}
