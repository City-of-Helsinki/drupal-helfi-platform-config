<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_csp\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Database\StatementInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\State\StateInterface;
use Drupal\helfi_csp\CspLogService;
use Drupal\helfi_csp\Hook\CronHook;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Unit tests for CronHook.
 */
#[Group('helfi_csp')]
#[CoversClass(CronHook::class)]
class CronHookTest extends UnitTestCase {

  /**
   * Builds a config factory mock for cron hook tests.
   *
   * @param string|null $reportingPlugin
   *   Value for csp.settings enforce.reporting.plugin.
   * @param int $timeWindow
   *   Value for helfi_csp.settings time_window.
   * @param int $treshold
   *   Value for helfi_csp.settings treshold.
   * @param bool $stopSending
   *   Value for helfi_csp.settings stop_sending.
   *
   * @return \Drupal\Core\Config\ConfigFactoryInterface
   *   Config factory mock.
   */
  private function createConfigFactory(
    ?string $reportingPlugin = 'csp_log',
    int $timeWindow = 3600,
    int $treshold = 5,
    bool $stopSending = FALSE,
  ): ConfigFactoryInterface {
    $cspConfig = $this->createMock(ImmutableConfig::class);
    $cspConfig->method('get')
      ->with('enforce.reporting.plugin')
      ->willReturn($reportingPlugin);

    $helfiConfig = $this->createMock(ImmutableConfig::class);
    $helfiConfig->method('get')->willReturnCallback(
      static fn (string $key): mixed => match ($key) {
        'time_window' => $timeWindow,
        'treshold' => $treshold,
        'stop_sending' => $stopSending,
        default => 0,
      },
    );

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturnMap([
      ['csp.settings', $cspConfig],
      ['helfi_csp.settings', $helfiConfig],
    ]);

    return $configFactory;
  }

  /**
   * Creates a time mock returning a fixed request time.
   *
   * @return \Drupal\Component\Datetime\TimeInterface
   *   Time mock.
   */
  private function createTimeMock(): TimeInterface {
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(2_000_000);
    return $time;
  }

  /**
   * Creates CspLogService with a database mock for aggregation queries.
   *
   * @param array<array<string, mixed>> $aggregatedLogs
   *   Rows returned by fetchAggregatedLogsByTimeWindow().
   * @param string|null $sampleReport
   *   Raw report JSON for fetchLogSample(), or NULL for no sample row.
   *
   * @return \Drupal\helfi_csp\CspLogService
   *   Service under test.
   */
  private function createCspLogService(
    array $aggregatedLogs,
    ?string $sampleReport = NULL,
  ): CspLogService {
    $aggregatedStatement = $this->createMock(StatementInterface::class);
    $aggregatedStatement->method('fetchAll')
      ->with(\PDO::FETCH_ASSOC)
      ->willReturn($aggregatedLogs);

    $statements = [$aggregatedStatement];

    if ($sampleReport !== NULL) {
      $sampleStatement = $this->createMock(StatementInterface::class);
      $sampleStatement->method('fetchAssoc')
        ->willReturn(['report' => $sampleReport]);
      $statements[] = $sampleStatement;
    }

    $select = $this->createMock(SelectInterface::class);
    $select->method('addField')->willReturnSelf();
    $select->method('addExpression')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('groupBy')->willReturnSelf();
    $select->method('having')->willReturnSelf();
    $select->method('orderBy')->willReturnSelf();
    $select->expects($this->exactly(count($statements)))
      ->method('execute')
      ->willReturnOnConsecutiveCalls(...$statements);

    $connection = $this->createMock(Connection::class);
    $connection->method('select')->willReturn($select);

    return new CspLogService(
      $this->createMock(RequestStack::class),
      $this->createMock(LockBackendInterface::class),
      $this->createMock(StateInterface::class),
      $connection,
      $this->createMock(LoggerInterface::class),
      $this->createTimeMock(),
      $this->createMock(ConfigFactoryInterface::class),
    );
  }

  /**
   * Creates CspLogService backed by a database that must not be queried.
   *
   * @return \Drupal\helfi_csp\CspLogService
   *   Service under test.
   */
  private function createCspLogServiceWithoutQueries(): CspLogService {
    $connection = $this->createMock(Connection::class);
    $connection->expects($this->never())->method('select');

    return new CspLogService(
      $this->createMock(RequestStack::class),
      $this->createMock(LockBackendInterface::class),
      $this->createMock(StateInterface::class),
      $connection,
      $this->createMock(LoggerInterface::class),
      $this->createTimeMock(),
      $this->createMock(ConfigFactoryInterface::class),
    );
  }

  /**
   * Builds CronHook with the given dependencies.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger.
   * @param \Drupal\helfi_csp\CspLogService|null $cspLogService
   *   Optional CSP log service.
   *
   * @return \Drupal\helfi_csp\Hook\CronHook
   *   Hook under test.
   */
  private function createCronHook(
    ConfigFactoryInterface $configFactory,
    LoggerInterface $logger,
    ?CspLogService $cspLogService = NULL,
  ): CronHook {
    $cronHook = new CronHook($logger, $configFactory);
    if ($cspLogService !== NULL) {
      $cronHook->setCspLogService($cspLogService);
    }
    return $cronHook;
  }

  /**
   * Does nothing when the CSP log service was not injected.
   */
  public function testDoesNothingWhenCspLogServiceNotSet(): void {
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->expects($this->never())->method('get');

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->never())->method('error');

    ($this->createCronHook($configFactory, $logger))();
  }

  /**
   * Does nothing when CSP reporting plugin is not csp_log.
   */
  public function testDoesNothingWhenReportingPluginIsNotCspLog(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->never())->method('error');

    ($this->createCronHook(
      $this->createConfigFactory('none'),
      $logger,
      $this->createCspLogServiceWithoutQueries(),
    ))();
  }

  /**
   * Does not log when no aggregated violations exceed the threshold.
   */
  public function testDoesNothingWhenNoAggregatedLogs(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->never())->method('error');

    ($this->createCronHook(
      $this->createConfigFactory('csp_log', 7200, 10),
      $logger,
      $this->createCspLogService([]),
    ))();
  }

  /**
   * Stops cron handling when stop_sending is enabled.
   */
  public function testStopsSendingWhenConfigured(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('info')
      ->with('Aggregated CSP-violation report handling currently switched off.');
    $logger->expects($this->never())->method('error');

    ($this->createCronHook(
      $this->createConfigFactory('csp_log', 3600, 5, TRUE),
      $logger,
      $this->createCspLogServiceWithoutQueries(),
    ))();
  }

  /**
   * Logs each aggregated violation to Sentry with a report sample.
   */
  public function testLogsViolationsWhenConfigured(): void {
    $sampleReport = '{"violated-directive":"script-src"}';

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with(
        'CSP violation in {document_uri}',
        [
          'document_uri' => 'https://hel.fi/page',
          '@blocked_uri' => 'https://evil.com/script.js',
          '@effective_directive' => 'script-src',
          '@amount' => 12,
          '@time_window' => 3600,
          '@treshold' => 5,
          '@sample' => $sampleReport,
        ],
      );

    ($this->createCronHook(
      $this->createConfigFactory(),
      $logger,
      $this->createCspLogService(
        [
          [
            'document_uri' => 'https://hel.fi/page',
            'blocked_uri' => 'https://evil.com/script.js',
            'effective_directive' => 'script-src',
            'amount' => 12,
          ],
        ],
        $sampleReport,
      ),
    ))();
  }

}
