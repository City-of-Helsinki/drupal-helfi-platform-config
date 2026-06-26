<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Render;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Render\AttachmentsInterface;
use Drupal\Core\Render\AttachmentsResponseProcessorInterface;
use Drupal\helfi_platform_config\Asset\JsCspHashCollector;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

/**
 * Merges CSP hashes collected during JavaScript rendering into the response.
 */
#[AsDecorator(decorates: 'html_response.attachments_processor')]
class CspHashAttachmentsProcessor implements AttachmentsResponseProcessorInterface {

  /**
   * Constructs a CspHashAttachmentsProcessor.
   */
  public function __construct(
    private AttachmentsResponseProcessorInterface $inner,
    private JsCspHashCollector $hashCollector,
    private ConfigFactoryInterface $configFactory,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function processAttachments(AttachmentsInterface $response) {
    if (!$this->isEnabled()) {
      return $this->inner->processAttachments($response);
    }

    $this->hashCollector->reset();
    $response = $this->inner->processAttachments($response);

    $hashes = $this->hashCollector->getHashes();
    if ($hashes === []) {
      return $response;
    }

    $attachments = $response->getAttachments();

    foreach ($hashes as $directive => $directive_hashes) {
      $attachments['csp_hash'][$directive] = array_merge(
        $attachments['csp_hash'][$directive] ?? [],
        $directive_hashes,
      );
    }

    $response->setAttachments($attachments);

    return $response;
  }

  /**
   * Check if external script hashes are enabled.
   */
  private function isEnabled(): bool {
    return (bool) $this->configFactory
      ->get('helfi_platform_config.csp')
      ->get('external_script_hashes');
  }

}
