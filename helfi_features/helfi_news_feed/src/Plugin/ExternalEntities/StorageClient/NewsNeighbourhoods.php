<?php

declare(strict_types = 1);

namespace Drupal\helfi_news_feed\Plugin\ExternalEntities\StorageClient;

use Drupal\helfi_news_feed\HelfiExternalEntityBase;

/**
 * External entity storage client for News neighbourhoods taxonomy terms.
 *
 * @ExternalEntityStorageClient(
 *   id = "helfi_news_neighbourhoods",
 *   label = @Translation("Helfi: News neighbourhoods"),
 *   description = @Translation("Retrieves news neighbourhoods taxonomy terms from Helfi")
 * )
 */
final class NewsNeighbourhoods extends HelfiExternalEntityBase {

  /**
   * Query parameters.
   *
   * @var array|string[]
   */
  protected array $query = [
    'fields[taxonomy_term--news_neighbourhoods]' => 'id,name,changed,langcode,status',
  ];

  /**
   * Json api endpoint for taxonomy term.
   *
   * @var string
   */
  protected string $endpoint = '/jsonapi/taxonomy_term/news_neighbourhoods';

}
