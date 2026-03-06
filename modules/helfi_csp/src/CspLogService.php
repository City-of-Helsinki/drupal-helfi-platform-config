<?php

declare(strict_types=1);

namespace Drupal\helfi_csp;

use Drupal\Core\Database\Connection;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\State\StateInterface;
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
   * State key to enable lock-based rate limiting (one log per second).
   *
   * Set to TRUE via drush: drush state:set helfi_csp.rate_limit_enabled 1
   * Disabled by default.
   */
  private const STATE_RATE_LIMIT_ENABLED = 'helfi_csp.rate_limit_enabled';

  /**
   * Lock name for rate limiting CSP log writes (one per second).
   *
   * Lock is acquired with a 1s timeout so it auto-expires; we do not release
   * it, so the next request can only acquire after the lock has expired.
   */
  private const LOCK_NAME = 'helfi_csp_log_rate_limit';

  public function __construct(
    private readonly RequestStack $requestStack,
    #[Autowire('@lock')]
    private readonly LockBackendInterface $lock,
    private readonly StateInterface $state,
    #[Autowire('@database')] Connection $database,
    #[Autowire('@logger.channel.csp_log')] LoggerInterface $logger,
  ) {
    parent::__construct($database, $logger);
  }

  /**
   * {@inheritDoc}
   */
  public function insertLog(object $data, string $type) {
    if ($this->shouldFilterReport($data)) {
      return;
    }
    if ($this->state->get(self::STATE_RATE_LIMIT_ENABLED, FALSE)) {
      // Acquire with 1s timeout so lock auto-expires; we don't release, so at
      // most one log per second from concurrent requests. The lock is released
      // automatically at the end of the request, so we might get more than one
      // log per second from sequential requests.
      if (!$this->lock->acquire(self::LOCK_NAME, 1)) {
        return;
      }
    }
    parent::insertLog($data, $type);
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
    // Allow same host, and strip optional leading 'www.' for comparison.
    $normalize = function ($host) {
      return preg_replace('#^www\.#i', '', $host);
    };
    return $normalize($documentHost) === $normalize($currentHost);
  }

}
