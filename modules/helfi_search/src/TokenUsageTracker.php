<?php

declare(strict_types=1);

namespace Drupal\helfi_search;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Utility\Error;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Service for tracking OpenAI token usage.
 */
class TokenUsageTracker {

  public function __construct(
    private readonly Connection $database,
    #[Autowire(service: 'logger.channel.helfi_search')]
    private readonly LoggerInterface $logger,
    private readonly ConfigFactoryInterface $configFactory,
  ) {
  }

  /**
   * Update token usage for a specific model.
   *
   * @param string $model
   *   The model name.
   * @param int $tokens
   *   Number of tokens to add.
   */
  public function updateTokenUsage(string $model, int $tokens): void {
    try {
      $this->database->merge('helfi_search_token_usage')
        ->key('model_name', $model)
        ->fields([
          'total_tokens' => $tokens,
        ])
        ->expression('total_tokens', 'total_tokens + :tokens', [':tokens' => $tokens])
        ->execute();
    }
    catch (\Exception $e) {
      Error::logException($this->logger, $e);
    }
  }

  /**
   * Get token usage for a specific model.
   *
   * @param string|null $model
   *   The model name, or NULL to get all models.
   *
   * @return int|array<string,int>
   *   Token usage data.
   *
   * @throws \Exception
   */
  public function getTokenUsage(?string $model = NULL): int|array {
    $query = $this->database->select('helfi_search_token_usage', 'tu')
      ->fields('tu', ['model_name', 'total_tokens']);

    if ($model) {
      $query->condition('model_name', $model);
      return (int) $query->execute()->fetchField(1);
    }

    return array_map(static fn ($row) => (int) $row, $query->execute()->fetchAllKeyed());
  }

  /**
   * Calculate cost for a given model and token count.
   *
   * @param string $model
   *   The model name.
   * @param int $tokens
   *   Number of tokens.
   *
   * @return float|null
   *   The cost in dollars, or NULL if no pricing is configured for the model.
   */
  public function getUsageCost(string $model, int $tokens): ?float {
    $pricing = $this->configFactory->get('helfi_search.settings')->get('openai_model_pricing') ?? [];
    $price = $pricing[$model] ?? NULL;

    if ($price === NULL) {
      return NULL;
    }

    return $tokens / 1_000_000 * $price;
  }

}
