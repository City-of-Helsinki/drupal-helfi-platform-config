<?php

declare(strict_types=1);

namespace Drupal\helfi_search\OpenAI\DTO;

/**
 * A DTO for api collection.
 */
final readonly class Response {

  /**
   * Constructs a new instance.
   *
   * @param string $model
   *   Model name.
   * @param float[]|array<float[]> $embedding
   *   Embedding vectors.
   * @param int $total_tokens
   *   Total tokens used.
   */
  public function __construct(
    public string $model,
    public array $embedding,
    public int $total_tokens,
  ) {
  }

}
