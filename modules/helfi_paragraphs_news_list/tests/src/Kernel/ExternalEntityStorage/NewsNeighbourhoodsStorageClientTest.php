<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_news_list\Kernel\ExternalEntityStorage;

/**
 * Tests news tags storage client.
 *
 * @group helfi_paragraphs_news_list
 */
class NewsNeighbourhoodsStorageClientTest extends TermStorageClientTestBase {

  /**
   * {@inheritdoc}
   */
  protected function getStorageName(): string {
    return 'helfi_news_neighbourhoods';
  }

  /**
   * {@inheritdoc}
   */
  protected function getVid(): string {
    return 'news_neighbourhoods';
  }

}
