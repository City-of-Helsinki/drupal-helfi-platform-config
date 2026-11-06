<?php

declare(strict_types=1);

namespace Drupal\helfi_search;

/**
 * The embedding models supported by helfi search.
 */
enum EmbeddingModel: string {

  /**
   * Default model.
   */
  public const self DEFAULT = self::Large;

  /**
   * Currently enabled models.
   *
   * Currently, we only use `text-embedding-3-large`, and this is not
   * that useful. However, if we ever need to change the model, the
   * process is:
   *  - Add the new to enabled models.
   *  - Re-index the content, embeddings are generated for all enabled models.
   *  - Test that everything works. You can use `?model=new-model`
   *    parameter which model is used to generate search results.
   *  - Once confident, change the DEFAULT constant.
   *
   * @phpstan-var self[]
   */
  public const array ENABLED = [self::DEFAULT];

  case Small = 'text-embedding-3-small';
  case Large = 'text-embedding-3-large';

  /**
   * The dimensions of the embedding model.
   *
   * This is used to set the dims-parameter of the embeddings-field mapping in
   * the Elasticsearch index. The value should always match the default
   * dimensions-value of the model in the API.
   */
  public function dimensions(): int {
    // We do not set a default match case to catch a use case where a new model
    // was added in the future without a matching dimension.
    return match ($this) {
      self::Small => 1536,
      self::Large => 3072,
    };
  }

  /**
   * The embeddings field prefix for this model.
   */
  public function fieldPrefix(): string {
    return 'embeddings_' . preg_replace('/[^a-z0-9]/', '_', strtolower($this->value));
  }

}
