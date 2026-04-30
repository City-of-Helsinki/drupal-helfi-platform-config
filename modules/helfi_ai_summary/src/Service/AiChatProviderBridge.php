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
   *
   * @return array{provider_id: \Drupal\ai\OperationType\Chat\ChatInterface, model_id: string}
   *   The provider and model ID.
   */
  public function getSetProvider(string $operation_type, ?string $preferred_model = NULL): array {
    /** @var array{provider_id: \Drupal\ai\OperationType\Chat\ChatInterface, model_id: string} $result */
    $result = $this->manager->getSetProvider($operation_type, $preferred_model);
    return $result;
  }

}
