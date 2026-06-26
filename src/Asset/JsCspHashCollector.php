<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Asset;

use Drupal\csp\Csp;

/**
 * Collects CSP hashes for scripts during asset rendering.
 *
 * Populated by JsCollectionRenderer and merged into response attachments by
 * CspHashAttachmentsProcessor.
 */
class JsCspHashCollector {

  /**
   * Collected hashes keyed by CSP directive.
   *
   * @var array<string, array<string, string[]>>
   */
  private array $hashes = [];

  /**
   * Reset collected hashes.
   */
  public function reset(): void {
    $this->hashes = [];
  }

  /**
   * Record a hash for a script allowed via integrity metadata.
   *
   * @param string $hash
   *   Hash in the form "sha256-{base64-value}" matching the integrity attribute.
   */
  public function addScriptHash(string $hash): void {
    $this->hashes['script-src-elem'][$hash] = [Csp::POLICY_UNSAFE_INLINE];
  }

  /**
   * Get collected hashes for response attachments.
   *
   * @return array<string, array<string, string[]>>
   *   Hashes in #attached['csp_hash'] format.
   */
  public function getHashes(): array {
    return $this->hashes;
  }

}
