<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_news_list\Plugin\ExternalEntities\StorageClient;

use Drupal\Core\Field\BaseFieldDefinition;

/**
 * External entity storage client for News neighbourhoods taxonomy terms.
 *
 * @StorageClient(
 *   id = "helfi_news_neighbourhoods",
 *   label = @Translation("Helfi: News neighbourhoods"),
 *   description = @Translation("Retrieves news neighbourhoods taxonomy terms from Helfi")
 * )
 */
final class NewsNeighbourhoods extends TermBase {

  /**
   * {@inheritdoc}
   */
  protected string $vid = 'news_neighbourhoods';

}
