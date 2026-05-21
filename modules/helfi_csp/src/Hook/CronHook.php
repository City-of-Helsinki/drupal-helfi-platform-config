<?php

declare(strict_types=1);

namespace Drupal\helfi_csp\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\csp_log\CspLogServiceInterface;
use Drupal\helfi_csp\CspLogService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Implements hook_cron().
 */
#[Hook('cron')]
class CronHook {

  public function __construct(
    #[Autowire('@csp_log')]
    private readonly CspLogServiceInterface $cspLogService,
    #[Autowire('@logger.channel.helfi_csp')]
    private readonly LoggerInterface $logger,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Implements hook_cron().
   */
  public function __invoke(): void {
    assert($this->cspLogService instanceof CspLogService);

    // Only proceed if CSP logging is enabled.
    $cspConfig = $this->configFactory->get('csp.settings');
    if ($cspConfig->get('enforce.reporting.plugin') !== 'csp_log') {
      return;
    }

    $config = $this->configFactory->get('helfi_csp.settings');
    $timeWindow = $config->get('time_window');
    $treshold = $config->get('treshold');

    // Fetch aggregated logs matching the time window and treshold.
    $logs = $this->cspLogService->fetchAggregatedLogsByTimeWindow($timeWindow, $treshold);

    foreach ($logs as $log) {
      // Send a notification to Sentry.
      $this->logger->error('CSP violation in {document_uri}', [
        'document_uri' => $log->document_uri,
        '@blocked_uri' => $log->blocked_uri,
        '@effective_directive' => $log->effective_directive,
        '@amount' => $log->amount,
        '@time_window' => $timeWindow,
        '@treshold' => $treshold,
        '@sample' => $this->cspLogService->fetchLogSample($log->document_uri, $log->blocked_uri, $log->effective_directive),
      ]);
    }
  }

}
