<?php

declare(strict_types=1);

namespace Drupal\helfi_ai_summary\Service;

use Drupal\ai\AiProviderPluginManager;

/**
 * Bridges the final AiProviderPluginManager to AiChatProviderInterface.
 */
final class AiChatProviderBridge implements AiChatProviderInterface {

  public function __construct(
    private readonly AiProviderPluginManager $manager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getSetProvider(string $operation_type, ?string $preferred_model = NULL): array {
    return $this->manager->getSetProvider($operation_type, $preferred_model);
  }

}
