<?php

declare(strict_types=1);

namespace Drupal\helfi_ai\Service;

use Drupal\ai\AiProviderPluginManager;

/**
 * Adapts the AI module's provider manager to a mockable local interface.
 *
 * Delegates to the ai.provider service (AiProviderPluginManager) and changes
 * nothing about its behaviour. It exists purely as a test seam: that manager
 * is a final class with no interface, so it cannot be mocked directly.
 * Depending on AiChatProviderInterface instead lets AiSummaryGenerator be unit
 * tested with a mocked provider.
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
