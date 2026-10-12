<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_csp\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\Flood\FloodInterface;
use Drupal\csp_log\CspLogServiceInterface;
use Drupal\helfi_csp\CspLogService;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Unit tests for CspLogService filtering, size limits and rate limiting.
 */
#[CoversClass(CspLogService::class)]
#[Group('helfi_csp')]
final class CspLogServiceTest extends UnitTestCase {

  /**
   * The fields of the inserted rows.
   *
   * @var \ArrayObject<int, array<string, mixed>>
   */
  private \ArrayObject $inserted;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->inserted = new \ArrayObject();
  }

  /**
   * Builds a valid CSP report object (required keys for base service).
   *
   * @param string $documentUri
   *   Document-uri value.
   * @param string $blockedUri
   *   Blocked-uri value.
   *
   * @return \stdClass
   *   Report data object.
   */
  private static function report(string $documentUri, string $blockedUri): \stdClass {
    return (object) [
      'document-uri' => $documentUri,
      'effective-directive' => 'script-src',
      'blocked-uri' => $blockedUri,
    ];
  }

  /**
   * Creates a request stack that returns a request with the given host.
   *
   * @param string|null $host
   *   Host for getCurrentRequest()->getHost(), or NULL for no request.
   *
   * @return \Symfony\Component\HttpFoundation\RequestStack
   *   Request stack mock.
   */
  private function createRequestStack(?string $host): RequestStack {
    $stack = $this->createMock(RequestStack::class);
    if ($host === NULL) {
      $stack->method('getCurrentRequest')->willReturn(NULL);
      return $stack;
    }
    $request = $this->createMock(Request::class);
    $request->method('getHost')->willReturn($host);
    $stack->method('getCurrentRequest')->willReturn($request);
    return $stack;
  }

  /**
   * Creates a connection mock that expects insert() never to be called.
   *
   * @return \Drupal\Core\Database\Connection
   *   Connection mock.
   */
  private function createConnectionMockNeverInsert(): Connection {
    $connection = $this->createMock(Connection::class);
    $connection->expects($this->never())->method('insert');
    return $connection;
  }

  /**
   * Creates a connection mock expecting one insert() and returning a chain.
   *
   * The inserted fields are collected to $this->inserted.
   *
   * @return \Drupal\Core\Database\Connection
   *   Connection mock.
   */
  private function createConnectionMockInsertOnce(): Connection {
    $insertStub = new class($this->inserted) {

      /**
       * Constructs a new instance.
       *
       * @param \ArrayObject<int, array<string, mixed>> $inserted
       *   The inserted fields.
       */
      public function __construct(private \ArrayObject $inserted) {
      }

      /**
       * Stub for Insert::fields().
       *
       * @phpstan-param array<mixed> $f
       */
      public function fields(array $f): self {
        $this->inserted->append($f);
        return $this;
      }

      /**
       * Stub for Insert::execute().
       */
      public function execute(): void {
      }

    };
    $connection = $this->createMock(Connection::class);
    $connection->expects($this->once())
      ->method('insert')
      ->with(CspLogServiceInterface::DATABASE_TABLE)
      ->willReturn($insertStub);
    return $connection;
  }

  /**
   * Creates a time mock returning a fixed request time.
   *
   * @param int $requestTime
   *   Request timestamp.
   *
   * @return \Drupal\Component\Datetime\TimeInterface
   *   Time mock.
   */
  private function createTimeMock(int $requestTime = 2_000_000): TimeInterface {
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn($requestTime);
    return $time;
  }

  /**
   * Builds a config factory mock for CspLogService tests.
   *
   * @param bool $stopRecieving
   *   Value for helfi_csp.settings stop_recieving.
   * @param int $rateLimit
   *   Value for helfi_csp.settings rate_limit.
   *
   * @return \Drupal\Core\Config\ConfigFactoryInterface
   *   Config factory mock.
   */
  private function createConfigFactory(bool $stopRecieving = FALSE, int $rateLimit = 0): ConfigFactoryInterface {
    $helfiConfig = $this->createMock(ImmutableConfig::class);
    $helfiConfig->method('get')
      ->willReturnMap([
        ['stop_recieving', $stopRecieving],
        ['rate_limit', $rateLimit],
      ]);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('helfi_csp.settings')
      ->willReturn($helfiConfig);

    return $configFactory;
  }

  /**
   * Creates a flood mock that expects not to be used.
   *
   * @return \Drupal\Core\Flood\FloodInterface
   *   Flood mock.
   */
  private function createFloodMockNeverUsed(): FloodInterface {
    $flood = $this->createMock(FloodInterface::class);
    $flood->expects($this->never())->method('isAllowed');
    $flood->expects($this->never())->method('register');
    return $flood;
  }

  /**
   * Creates the service under test with the given mocks.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request stack.
   * @param \Drupal\Core\Flood\FloodInterface $flood
   *   Flood.
   * @param \Drupal\Core\Database\Connection $connection
   *   Database connection.
   * @param \Drupal\Component\Datetime\TimeInterface|null $time
   *   Time service; defaults to a fixed request time.
   * @param \Drupal\Core\Config\ConfigFactoryInterface|null $configFactory
   *   Config factory; defaults to stop_recieving and rate limit disabled.
   *
   * @return \Drupal\helfi_csp\CspLogService
   *   Service under test.
   */
  private function createSut(
    RequestStack $requestStack,
    FloodInterface $flood,
    Connection $connection,
    ?TimeInterface $time = NULL,
    ?ConfigFactoryInterface $configFactory = NULL,
  ): CspLogService {
    $logger = $this->createMock(LoggerInterface::class);
    return new CspLogService(
      $requestStack,
      $flood,
      $connection,
      $logger,
      $time ?? $this->createTimeMock(),
      $configFactory ?? $this->createConfigFactory(),
    );
  }

  /**
   * Data provider for filtered reports that must not be logged.
   *
   * @return array<string, array{string, string, string|null}>
   *   Each element: document-uri, blocked-uri, request host (or null).
   */
  public static function providerFilteredDoesNotLog(): array {
    return [
      'blocked-uri matches browser scheme' => [
        'https://hel.fi/page',
        'chrome-extension://some-extension/script.js',
        'hel.fi',
      ],
      'blocked-uri contains blocked domain' => [
        'https://hel.fi/page',
        'https://translate.googleapis.com/translate_static/js/element/main.js',
        'hel.fi',
      ],
      'document-uri is off-site' => [
        'https://evil.com/page',
        'https://evil.com/script.js',
        'hel.fi',
      ],
      'no current request' => [
        'https://hel.fi/page',
        'https://example.com/script.js',
        NULL,
      ],
      'document-uri has no host' => [
        'about:blank',
        'https://example.com/script.js',
        'hel.fi',
      ],
      'document-uri root domain is not in allowlist' => [
        'https://example.com/page',
        'https://example.com/script.js',
        'example.com',
      ],
      'document-uri root domain differs from request' => [
        'https://sub.hel.ninja/page',
        'https://sub.hel.ninja/script.js',
        'hel.fi',
      ],
      'document-uri host only ends with the root domain' => [
        'https://evilhel.fi/page',
        'https://evilhel.fi/script.js',
        'www.hel.fi',
      ],
    ];
  }

  /**
   * Filtered reports are not logged.
   */
  #[DataProvider('providerFilteredDoesNotLog')]
  public function testFilteredReportDoesNotLog(
    string $documentUri,
    string $blockedUri,
    ?string $requestHost,
  ): void {
    $sut = $this->createSut(
      $this->createRequestStack($requestHost),
      $this->createFloodMockNeverUsed(),
      $this->createConnectionMockNeverInsert(),
    );

    $report = self::report($documentUri, $blockedUri);
    $sut->insertLog($report, 'report-only');
  }

  /**
   * Stops logging when stop_recieving is enabled.
   */
  public function testStopsReceivingWhenConfigured(): void {
    $sut = $this->createSut(
      $this->createRequestStack('hel.fi'),
      $this->createFloodMockNeverUsed(),
      $this->createConnectionMockNeverInsert(),
      NULL,
      $this->createConfigFactory(TRUE, 60),
    );

    $report = self::report('https://hel.fi/page', 'https://hel.fi/script.js');
    $sut->insertLog($report, 'report-only');
  }

  /**
   * Report that passes all filters is logged when rate limit is disabled.
   */
  public function testPassesFiltersReportIsLogged(): void {
    $sut = $this->createSut(
      $this->createRequestStack('hel.fi'),
      $this->createFloodMockNeverUsed(),
      $this->createConnectionMockInsertOnce(),
    );

    $report = self::report('https://foo.hel.fi/page', 'https://foo.hel.fi/script.js');
    $sut->insertLog($report, 'report-only');
  }

  /**
   * The large fields are not stored.
   */
  public function testLargeFieldsAreNotStored(): void {
    $sut = $this->createSut(
      $this->createRequestStack('hel.fi'),
      $this->createFloodMockNeverUsed(),
      $this->createConnectionMockInsertOnce(),
    );

    $report = self::report('https://hel.fi/page', 'https://hel.fi/script.js');
    $report->{'original-policy'} = str_repeat('script-src https://example.com; ', 1000);
    $report->{'script-sample'} = 'alert(1)';
    $sut->insertLog($report, 'report-only');

    $stored = json_decode($this->inserted[0]['report'], TRUE);
    $this->assertSame('https://hel.fi/script.js', $stored['blocked-uri']);
    $this->assertArrayNotHasKey('original-policy', $stored);
    $this->assertArrayNotHasKey('script-sample', $stored);
  }

  /**
   * The oversized reports are not stored.
   */
  public function testOversizedReportIsNotStored(): void {
    $sut = $this->createSut(
      $this->createRequestStack('hel.fi'),
      $this->createFloodMockNeverUsed(),
      $this->createConnectionMockNeverInsert(),
    );

    $report = self::report('https://hel.fi/page', 'https://hel.fi/script.js');
    $report->{'source-file'} = 'https://hel.fi/' . str_repeat('a', CspLogService::MAX_REPORT_SIZE);
    $sut->insertLog($report, 'report-only');
  }

  /**
   * Data provider for the rate limit: flood result and expect insert flag.
   *
   * @return array<string, array{bool}>
   *   Each element: whether the flood allows the report.
   */
  public static function providerRateLimit(): array {
    return [
      'limit reached, not logged' => [FALSE],
      'limit not reached, logged' => [TRUE],
    ];
  }

  /**
   * With the rate limit enabled, logging depends on the flood control.
   */
  #[DataProvider('providerRateLimit')]
  public function testRateLimit(bool $allowed): void {
    $flood = $this->createMock(FloodInterface::class);
    $flood->expects($this->once())
      ->method('isAllowed')
      ->with('helfi_csp.report', 60, 60)
      ->willReturn($allowed);
    $flood->expects($allowed ? $this->once() : $this->never())
      ->method('register')
      ->with('helfi_csp.report', 60);

    $sut = $this->createSut(
      $this->createRequestStack('hel.fi'),
      $flood,
      $allowed ? $this->createConnectionMockInsertOnce() : $this->createConnectionMockNeverInsert(),
      NULL,
      $this->createConfigFactory(FALSE, 60),
    );

    $report = self::report('https://hel.fi/page', 'https://hel.fi/script.js');
    $sut->insertLog($report, 'report-only');
  }

}
