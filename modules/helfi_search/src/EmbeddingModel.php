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
    // We need to make sure the dimensions match actual model versions, so we
    // match on the model name. And we don't set a default match case. This way
    // we should catch any use case where a model name was updated or added
    // without updating the dimensions.
    return match ($this->value) {
      'text-embedding-3-small' => 1536,
      'text-embedding-3-large' => 3072,
    };
  }

  /**
   * The embeddings field prefix for this model.
   */
  public function fieldPrefix(): string {
    return 'embeddings_' . preg_replace('/[^a-z0-9]/', '_', strtolower($this->value));
  }

}
