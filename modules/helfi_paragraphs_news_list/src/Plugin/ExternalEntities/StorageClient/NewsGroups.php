<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_news_list\Plugin\ExternalEntities\StorageClient;

/**
 * External entity storage client for News groups taxonomy terms.
 *
 * @ExternalEntityStorageClient(
 *   id = "helfi_news_groups",
 *   label = @Translation("Helfi: News groups"),
 *   description = @Translation("Retrieves news groups taxonomy terms from Helfi")
 * )
 */
final class NewsGroups extends TermBase {
}
