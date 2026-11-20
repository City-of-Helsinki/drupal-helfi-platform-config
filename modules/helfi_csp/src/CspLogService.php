<?php

declare(strict_types=1);

namespace Drupal\helfi_csp;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Statement\FetchAs;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\csp_log\CspLogService as BaseCspLogService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Decorates the base csp_log service with report filtering.
 */
#[AsDecorator(decorates: 'csp_log')]
final class CspLogService extends BaseCspLogService {

  /**
   * Domain allowlist.
   *
   * Only document-uri root domains in this allowlist are allowed to be logged.
   */
  protected const DOMAIN_ALLOWLIST = [
    'hel.fi',
    'hel.ninja',
    'docker.so',
  ];

  /**
   * Strings that cause a blocked-uri to be filtered (not logged).
   *
   * Matched by substring against the full blocked-uri. Includes browser
   * schemes (e.g. chrome-extension://), internal URIs (e.g. about:blank),
   * and known spam/malware/translate domains.
   */
  protected const BLOCKED_URI_PATTERNS = [
    // Browser/internal schemes and URIs.
    'resource://',
    'chromenull://',
    'chrome-extension://',
    'safari-extension://',
    'moz-extension://',
    'edge-extension://',
    'mxjscall://',
    'webviewprogressproxy://',
    'res://',
    'mx://',
    'safari-resource://',
    'chromeinvoke://',
    'chromeinvokeimmediate://',
    'mbinit://',
    'opera://',
    'ms-appx://',
    'ms-appx-web://',
    'localhost',
    '127.0.0.1',
    'none://',
    'about:blank',
    'android-webview',
    'ms-browser-extension',
    'wvjbscheme://__wvjb_queue_message__',
    'nativebaiduhd://adblock',
    'bdvideo://error',
    // Known spam/malware/translate domains.
    'infird.com',
    'extensionscontrol.com',
    'secured-pixel.com',
    'cdn.binsiad.com',
    'translate.googleapis.com',
    'www.gstatic.com',
    '2cdn.perplexity.ai',
    'sc-static.net',
    'assets.faircado.com',
    'cdn.scite.ai',
    'static.hsappstatic.net',
  ];

  /**
   * The flood event for rate limiting the reports per client.
   */
  private const FLOOD_EVENT = 'helfi_csp.report';

  /**
   * The rate limit window in seconds.
   */
  private const FLOOD_WINDOW = 60;

  /**
   * The report fields that are not stored.
   *
   * The original policy is the whole CSP policy, which makes up most of the
   * report size.
   */
  private const REMOVED_FIELDS = ['original-policy', 'script-sample'];

  /**
   * The maximum size of a stored report in bytes.
   */
  public const MAX_REPORT_SIZE = 10240;

  public function __construct(
    private readonly RequestStack $requestStack,
    private readonly FloodInterface $flood,
    #[Autowire('@database')] Connection $database,
    #[Autowire('@logger.channel.csp_log')] LoggerInterface $logger,
    private readonly TimeInterface $time,
    private readonly ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct($database, $logger);
  }

  /**
   * {@inheritDoc}
   */
  public function insertLog(object $data, string $type) {
    // We can stop writing any incoming reports to db by running:
    // @code
    // drush cset helfi_csp.settings stop_recieving 1
    // @endcode
    if ($this->configFactory->get('helfi_csp.settings')->get('stop_recieving')) {
      return;
    }

    if ($this->shouldFilterReport($data)) {
      return;
    }

    foreach (self::REMOVED_FIELDS as $field) {
      unset($data->{$field});
    }
    // Don't store the oversized reports.
    if (strlen((string) json_encode($data)) > self::MAX_REPORT_SIZE) {
      return;
    }

    // Limit the number of reports per client. Set the limit to 0 to disable
    // the rate limiting:
    // @code
    // drush cset helfi_csp.settings rate_limit 0
    // @endcode
    $rateLimit = (int) $this->configFactory->get('helfi_csp.settings')->get('rate_limit');
    if ($rateLimit > 0) {
      if (!$this->flood->isAllowed(self::FLOOD_EVENT, $rateLimit, self::FLOOD_WINDOW)) {
        return;
      }
      $this->flood->register(self::FLOOD_EVENT, self::FLOOD_WINDOW);
    }

    parent::insertLog($data, $type);
  }

  /**
   * Fetch aggregated logs to identify real problems.
   *
   * @param int $seconds
   *   The number of seconds to aggregate logs for. Defaults to 3600 seconds
   *   (1 hour).
   * @param int $treshold
   *   The minimum number of log reports within the time window to include in
   *   the aggregation. Defaults to a calculated value based on given time
   *   window and a rate of 100 reports per hour. If given $seconds is 3600,
   *   the default treshold will be 100.
   *
   * @return array<array<string, mixed>>>
   *   An array of aggregated logs by document-uri, blocked-uri, and
   *   effective-directive.
   */
  public function fetchAggregatedLogsByTimeWindow(int $seconds = 3600, ?int $treshold = NULL): array {
    if ($treshold === NULL) {
      $treshold = ceil(($seconds / 3600) * 100);
    }

    $start = $this->time->getRequestTime() - $seconds;
    $query = $this->database->select(self::DATABASE_TABLE, 't');
    $query->addField('t', 'document_uri');
    $query->addField('t', 'blocked_uri');
    $query->addField('t', 'effective_directive');
    $query->addExpression('COUNT(t.id)', 'amount');
    $query->condition('t.timestamp', $start, '>=');
    $query->groupBy('t.document_uri');
    $query->groupBy('t.blocked_uri');
    $query->groupBy('t.effective_directive');
    $query->having('amount >= :treshold', [':treshold' => $treshold]);
    $query->range(0, 100);

    return $query->execute()->fetchAll(FetchAs::Associative);
  }

  /**
   * Fetch log sample.
   *
   * @param string $documentUri
   *   The document-uri to fetch sample for.
   * @param string $blockedUri
   *   The blocked-uri to fetch sample for.
   * @param string $effectiveDirective
   *   The effective-directive to fetch sample for.
   *
   * @return string|null
   *   The latest log report matching the arguments, or NULL if no log is found.
   */
  public function fetchLogSample(string $documentUri, string $blockedUri, string $effectiveDirective): string|null {
    $sample = NULL;

    $query = $this->database->select(self::DATABASE_TABLE, 't');
    $query->addField('t', 'report');
    $query->condition('t.document_uri', $documentUri);
    $query->condition('t.blocked_uri', $blockedUri);
    $query->condition('t.effective_directive', $effectiveDirective);
    $query->orderBy('t.id', 'DESC');

    $result = $query->execute()->fetchAssoc();
    if (isset($result['report'])) {
      $sample = json_decode($result['report'], TRUE);

      // Remove the original csp policy from the sample to avoid flooding the
      // logs.
      unset($sample['original-policy']);

      $sample = json_encode($sample);
    }

    return $sample ?: NULL;
  }

  /**
   * Determines whether the CSP report should be filtered out (not logged).
   *
   * @param object $data
   *   The report data.
   *
   * @return bool
   *   TRUE if the report should be filtered out, FALSE otherwise.
   */
  protected function shouldFilterReport(object $data): bool {
    $blockedUri = $data->{'blocked-uri'} ?? '';
    $documentUri = $data->{'document-uri'} ?? '';

    if ($this->isBlockedUriFiltered($blockedUri)) {
      return TRUE;
    }
    if (!$this->isDocumentUriOnCurrentSite($documentUri)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Checks if blocked-uri contains any filtered pattern (scheme or domain).
   *
   * @param string $blockedUri
   *   The blocked-uri value.
   *
   * @return bool
   *   TRUE if the URI should be filtered.
   */
  protected function isBlockedUriFiltered(string $blockedUri): bool {
    $lower = strtolower($blockedUri);
    foreach (self::BLOCKED_URI_PATTERNS as $pattern) {
      if (str_contains($lower, strtolower($pattern))) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Checks if document-uri belongs to the current site domain.
   *
   * @param string $documentUri
   *   The document-uri value.
   *
   * @return bool
   *   TRUE if document is on current site (do not filter), FALSE otherwise.
   */
  protected function isDocumentUriOnCurrentSite(string $documentUri): bool {
    $request = $this->requestStack->getCurrentRequest();
    if (!$request) {
      return FALSE;
    }
    $currentHost = strtolower($request->getHost());
    $parsed = parse_url($documentUri);
    $documentHost = isset($parsed['host']) ? strtolower($parsed['host']) : '';

    if ($documentHost === '') {
      return FALSE;
    }

    // Allow if document host root domain:
    // 1. is in the allowlist
    // 2. matches the current host root domain.
    $documentHostRootDomain = $this->getRootDomain($documentHost);

    return $documentHostRootDomain && $documentHostRootDomain === $this->getRootDomain($currentHost);
  }

  /**
   * Gets the allowlisted root domain of the host.
   *
   * @param string $host
   *   The host.
   *
   * @return string|null
   *   The root domain, or NULL if the host isn't the root domain or its
   *   subdomain.
   */
  private function getRootDomain(string $host): ?string {
    return array_find(self::DOMAIN_ALLOWLIST, fn (string $domain) => $host === $domain || str_ends_with($host, '.' . $domain));
  }

}
