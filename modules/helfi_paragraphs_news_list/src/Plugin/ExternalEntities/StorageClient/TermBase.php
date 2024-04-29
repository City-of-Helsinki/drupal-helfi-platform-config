<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_news_list\Plugin\ExternalEntities\StorageClient;

use Drupal\helfi_paragraphs_news_list\ElasticExternalEntityBase;

/**
 * A base class for taxonomy terms.
 */
abstract class TermBase extends ElasticExternalEntityBase {

  /**
   * Elastic endpoint.
   *
   * @var string
   */
  protected string $index = 'news_terms';

  /**
   * {@inheritdoc}
   */
  protected function getFieldMapping(string $field) : string {
    return match($field) {
      'id' => 'uuid_langcode',
      'title' => 'name',
      default => $field,
    };
  }

}
