<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_news_list\Plugin\ExternalEntities\StorageClient;

use Drupal\helfi_paragraphs_news_list\ElasticExternalEntityBase;

/**
 * External entity storage client for News feed entities.
 *
 * @ExternalEntityStorageClient(
 *   id = "helfi_news",
 *   label = @Translation("Helfi: News"),
 *   description = @Translation("Retrieves 'news' content from Helfi")
 * )
 */
final class News extends ElasticExternalEntityBase {

  /**
   * {@inheritdoc}
   */
  protected string $index = 'news';

}
