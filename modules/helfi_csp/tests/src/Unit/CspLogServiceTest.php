<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_csp\Unit;

use Drupal\Core\Database\Connection;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\State\StateInterface;
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
 * Unit tests for CspLogService (filtering, locking, rate limiting).
 */
#[Group('helfi_csp')]
#[CoversClass(CspLogService::class)]
class CspLogServiceTest extends UnitTestCase {

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
   * @return \Drupal\Core\Database\Connection
   *   Connection mock.
   */
  private function createConnectionMockInsertOnce(): Connection {
    $insertStub = new class() {

      /**
       * Stub for Insert::fields().
       */
      public function fields(array $f) {
        return $this;
      }

      /**
       * Stub for Insert::execute().
       */
      public function execute() {
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
   * Creates the service under test with the given mocks.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request stack.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   Lock backend.
   * @param \Drupal\Core\State\StateInterface $state
   *   State.
   * @param \Drupal\Core\Database\Connection $connection
   *   Database connection.
   *
   * @return \Drupal\helfi_csp\CspLogService
   *   Service under test.
   */
  private function createSut(
    RequestStack $requestStack,
    LockBackendInterface $lock,
    StateInterface $state,
    Connection $connection,
  ): CspLogService {
    $logger = $this->createMock(LoggerInterface::class);
    return new CspLogService(
      $requestStack,
      $lock,
      $state,
      $connection,
      $logger,
    );
  }

  /**
   * Data provider for filtered reports that must not be logged.
   *
   * @return array[]
   *   Each element: document-uri, blocked-uri, request host (or null).
   */
  public static function providerFilteredDoesNotLog(): array {
    return [
      'blocked-uri matches browser scheme' => [
        'https://example.com/page',
        'chrome-extension://some-extension/script.js',
        'example.com',
      ],
      'blocked-uri contains blocked domain' => [
        'https://example.com/page',
        'https://translate.googleapis.com/translate_static/js/element/main.js',
        'example.com',
      ],
      'document-uri is off-site' => [
        'https://other-site.com/page',
        'https://evil.com/script.js',
        'example.com',
      ],
      'no current request' => [
        'https://any.com/page',
        'https://example.com/script.js',
        NULL,
      ],
      'document-uri has no host' => [
        'about:blank',
        'https://example.com/script.js',
        'example.com',
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
    $connection = $this->createConnectionMockNeverInsert();
    $lock = $this->createMock(LockBackendInterface::class);
    $state = $this->createMock(StateInterface::class);
    $state->method('get')->willReturn(FALSE);

    $sut = $this->createSut(
      $this->createRequestStack($requestHost),
      $lock,
      $state,
      $connection,
    );

    $report = self::report($documentUri, $blockedUri);
    $sut->insertLog($report, 'report-only');
  }

  /**
   * Report that passes all filters is logged when rate limit is disabled.
   */
  public function testPassesFiltersReportIsLogged(): void {
    $connection = $this->createConnectionMockInsertOnce();
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects($this->never())->method('acquire');
    $state = $this->createMock(StateInterface::class);
    $state->method('get')
      ->with('helfi_csp.rate_limit_enabled', FALSE)
      ->willReturn(FALSE);

    $sut = $this->createSut(
      $this->createRequestStack('example.com'),
      $lock,
      $state,
      $connection,
    );

    $report = self::report('https://example.com/page', 'https://example.com/script.js');
    $sut->insertLog($report, 'report-only');
  }

  /**
   * Data provider for rate limit (enabled): lock result and expect insert flag.
   *
   * @return array[]
   *   Each element: lock acquired (bool), expect insert (bool).
   */
  public static function providerRateLimitWhenEnabled(): array {
    return [
      'lock fails, not logged' => [FALSE, FALSE],
      'lock acquired, logged' => [TRUE, TRUE],
    ];
  }

  /**
   * With rate limit enabled, logging depends on lock acquisition.
   */
  #[DataProvider('providerRateLimitWhenEnabled')]
  public function testRateLimitWhenEnabled(bool $lockAcquired, bool $expectInsert): void {
    $connection = $expectInsert
      ? $this->createConnectionMockInsertOnce()
      : $this->createConnectionMockNeverInsert();

    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects($this->once())
      ->method('acquire')
      ->with('helfi_csp_log_rate_limit', 1)
      ->willReturn($lockAcquired);

    $state = $this->createMock(StateInterface::class);
    $state->method('get')
      ->with('helfi_csp.rate_limit_enabled', FALSE)
      ->willReturn(TRUE);

    $sut = $this->createSut(
      $this->createRequestStack('example.com'),
      $lock,
      $state,
      $connection,
    );

    $report = self::report(
      'https://example.com/page',
      'https://example.com/script.js',
    );
    $sut->insertLog($report, 'report-only');
  }

}
