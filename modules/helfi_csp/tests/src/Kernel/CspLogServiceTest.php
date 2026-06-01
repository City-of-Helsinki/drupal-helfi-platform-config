<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_csp\Kernel;

use Drupal\Core\Database\Connection;
use Drupal\csp_log\CspLogServiceInterface;
use Drupal\helfi_csp\CspLogService;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for CspLogService database queries.
 */
#[Group('helfi_csp')]
#[CoversClass(CspLogService::class)]
#[RunTestsInSeparateProcesses]
class CspLogServiceTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'csp_log',
    'helfi_csp',
  ];

  /**
   * Database connection.
   */
  private Connection $connection;

  /**
   * Decorated CSP log service under test.
   */
  private CspLogService $cspLogService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('csp_log', ['csp_log']);
    $this->connection = $this->container->get('database');
    $service = $this->container->get('csp_log');
    $this->assertInstanceOf(CspLogService::class, $service);
    $this->cspLogService = $service;
  }

  /**
   * Inserts CSP log rows sharing the same field values.
   *
   * @param int $count
   *   Number of rows to insert.
   * @param array<string, mixed> $fields
   *   Field values; merged over defaults.
   */
  private function insertCspLogRows(int $count, array $fields = []): void {
    $row = array_merge([
      'document_uri' => 'https://hel.fi/page',
      'effective_directive' => 'script-src',
      'blocked_uri' => 'https://evil.com/script.js',
      'referrer' => '',
      'type' => 'reportOnly',
      'report' => '{}',
      'timestamp' => $this->container->get('datetime.time')->getRequestTime(),
    ], $fields);

    $insert = $this->connection
      ->insert(CspLogServiceInterface::DATABASE_TABLE)
      ->fields(array_keys($row));
    for ($i = 0; $i < $count; $i++) {
      $insert->values($row);
    }
    $insert->execute();
  }

  /**
   * Aggregated logs only include groups meeting the explicit threshold.
   */
  public function testFetchAggregatedLogsByTimeWindowThreshold(): void {
    $timestamp = $this->container->get('datetime.time')->getRequestTime();

    $this->insertCspLogRows(24, [
      'document_uri' => 'https://hel.fi/low',
      'blocked_uri' => 'https://evil.com/low.js',
      'timestamp' => $timestamp,
    ]);
    $this->insertCspLogRows(25, [
      'document_uri' => 'https://hel.fi/high',
      'blocked_uri' => 'https://evil.com/high.js',
      'timestamp' => $timestamp,
    ]);

    $logs = $this->cspLogService->fetchAggregatedLogsByTimeWindow(3600, 25);

    $this->assertCount(1, $logs);
    $this->assertSame('https://hel.fi/high', $logs[0]['document_uri']);
    $this->assertSame('https://evil.com/high.js', $logs[0]['blocked_uri']);
    $this->assertGreaterThanOrEqual(25, (int) $logs[0]['amount']);
  }

  /**
   * Aggregated logs use a default threshold of 100 reports per hour.
   */
  public function testFetchAggregatedLogsByTimeWindowDefaultThreshold(): void {
    $timestamp = $this->container->get('datetime.time')->getRequestTime();

    $this->insertCspLogRows(99, [
      'document_uri' => 'https://hel.fi/below',
      'blocked_uri' => 'https://evil.com/below.js',
      'timestamp' => $timestamp,
    ]);
    $this->insertCspLogRows(100, [
      'document_uri' => 'https://hel.fi/at',
      'blocked_uri' => 'https://evil.com/at.js',
      'timestamp' => $timestamp,
    ]);

    $logs = $this->cspLogService->fetchAggregatedLogsByTimeWindow();

    $this->assertCount(1, $logs);
    $this->assertSame('https://hel.fi/at', $logs[0]['document_uri']);
    $this->assertGreaterThanOrEqual(100, (int) $logs[0]['amount']);
  }

  /**
   * Aggregated logs exclude rows outside the time window.
   */
  public function testFetchAggregatedLogsByTimeWindowExcludesOldRows(): void {
    $now = $this->container->get('datetime.time')->getRequestTime();

    $this->insertCspLogRows(30, [
      'document_uri' => 'https://hel.fi/page',
      'blocked_uri' => 'https://evil.com/script.js',
      'timestamp' => $now - 100,
    ]);
    $this->insertCspLogRows(30, [
      'document_uri' => 'https://hel.fi/page',
      'blocked_uri' => 'https://evil.com/script.js',
      'timestamp' => $now - 5000,
    ]);

    $logs = $this->cspLogService->fetchAggregatedLogsByTimeWindow(3600, 25);

    $this->assertCount(1, $logs);
    $this->assertSame(30, (int) $logs[0]['amount']);
  }

  /**
   * Log sample returns NULL when no matching row exists.
   */
  public function testFetchLogSampleReturnsNullWhenMissing(): void {
    $this->assertNull($this->cspLogService->fetchLogSample(
      'https://hel.fi/page',
      'https://evil.com/script.js',
      'script-src',
    ));
  }

  /**
   * Log sample returns the latest row and strips original-policy.
   */
  public function testFetchLogSampleReturnsLatestAndStripsOriginalPolicy(): void {
    $documentUri = 'https://hel.fi/page';
    $blockedUri = 'https://evil.com/script.js';
    $directive = 'script-src';

    $this->insertCspLogRows(1, [
      'document_uri' => $documentUri,
      'blocked_uri' => $blockedUri,
      'effective_directive' => $directive,
      'report' => json_encode([
        'document-uri' => $documentUri,
        'blocked-uri' => $blockedUri,
        'marker' => 'old',
      ]),
    ]);
    $this->insertCspLogRows(1, [
      'document_uri' => $documentUri,
      'blocked_uri' => $blockedUri,
      'effective_directive' => $directive,
      'report' => json_encode([
        'document-uri' => $documentUri,
        'blocked-uri' => $blockedUri,
        'original-policy' => 'default-src self',
        'marker' => 'new',
      ]),
    ]);

    $sample = $this->cspLogService->fetchLogSample($documentUri, $blockedUri, $directive);

    $decoded = json_decode($sample ?? '', TRUE);
    $this->assertIsArray($decoded);
    $this->assertArrayNotHasKey('original-policy', $decoded);
    $this->assertSame('new', $decoded['marker']);
  }

}
