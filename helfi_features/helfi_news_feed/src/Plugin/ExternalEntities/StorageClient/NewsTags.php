<?php

declare(strict_types = 1);

namespace Drupal\helfi_news_feed\Plugin\ExternalEntities\StorageClient;

use Drupal\helfi_news_feed\HelfiExternalEntityBase;

/**
 * External entity storage client for News tags taxonomy terms.
 *
 * @ExternalEntityStorageClient(
 *   id = "helfi_news_tags",
 *   label = @Translation("Helfi: News tags"),
 *   description = @Translation("Retrieves news tags taxonomy terms from Helfi")
 * )
 */
final class NewsTags extends HelfiExternalEntityBase {

  /**
   * Query parameters.
   *
   * @var array|string[]
   */
  protected array $query = [
    'fields[taxonomy_term--news_tags]' => 'id,name,changed,langcode,status',
  ];

  /**
   * Json api endpoint for taxonomy term.
   *
   * @var string
   */
  protected string $endpoint = '/jsonapi/taxonomy_term/news_tags';

}
