<?php

declare(strict_types=1);

namespace Drupal\helfi_ai_summary\Service;

/**
 * Narrow interface for resolving the configured chat provider.
 */
interface AiChatProviderInterface {

  /**
   * Returns the set chat provider and model.
   *
   * @param string $operation_type
   *   The operation type (e.g. 'chat').
   * @param string|null $preferred_model
   *   Optional preferred model ID.
   *
   * @return array{provider_id: object, model_id: string}
   *   Associative array with 'provider_id' and 'model_id'.
   */
  public function getSetProvider(string $operation_type, ?string $preferred_model = NULL): array;

}
