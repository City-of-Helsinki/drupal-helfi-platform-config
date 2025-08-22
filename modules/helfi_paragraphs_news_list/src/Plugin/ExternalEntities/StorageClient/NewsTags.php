<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_news_list\Plugin\ExternalEntities\StorageClient;

/**
 * External entity storage client for News tags taxonomy terms.
 *
 * @StorageClient(
 *   id = "helfi_news_tags",
 *   label = @Translation("Helfi: News tags"),
 *   description = @Translation("Retrieves news tags taxonomy terms from Helfi")
 * )
 */
final class NewsTags extends TermBase {

  /**
   * {@inheritdoc}
   */
  protected string $vid = 'news_tags';

  public function querySource(array $parameters = [], array $sorts = [], ?int $start = NULL, ?int $length = NULL): array {
    // @todo Implement
    return [];
  }

  public function transliterateDrupalFilters(array $parameters, array $context = []): array {
    // @todo Implement
    return [];
  }
}
