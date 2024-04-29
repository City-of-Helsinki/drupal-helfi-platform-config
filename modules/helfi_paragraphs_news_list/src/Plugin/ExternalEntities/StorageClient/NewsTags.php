<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_news_list\Plugin\ExternalEntities\StorageClient;

use Drupal\helfi_paragraphs_news_list\ElasticExternalEntityBase;

/**
 * External entity storage client for News tags taxonomy terms.
 *
 * @ExternalEntityStorageClient(
 *   id = "helfi_news_tags",
 *   label = @Translation("Helfi: News tags"),
 *   description = @Translation("Retrieves news tags taxonomy terms from Helfi")
 * )
 */
final class NewsTags extends ElasticExternalEntityBase {

  /**
   * Query parameters.
   *
   * @var array|string[]
   */
  protected array $query = [
    'fields[taxonomy_term--news_tags]' => 'id,name,changed,langcode,status,drupal_internal__tid',
  ];

  /**
   * Json api endpoint for taxonomy term.
   *
   * @var string
   */
  protected string $index = '/jsonapi/taxonomy_term/news_tags';

}
