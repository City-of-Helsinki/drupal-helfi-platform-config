<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_news_list\Kernel\ExternalEntityStorage;

/**
 * Tests news group storage client.
 *
 * @group helfi_paragraphs_news_list
 */
class NewsGroupStorageClientTest extends TermStorageClientTestBase {

  /**
   * {@inheritdoc}
   */
  protected function getStorageName(): string {
    return 'helfi_news_groups';
  }

  /**
   * {@inheritdoc}
   */
  protected function getVid(): string {
    return 'news_group';
  }

}
