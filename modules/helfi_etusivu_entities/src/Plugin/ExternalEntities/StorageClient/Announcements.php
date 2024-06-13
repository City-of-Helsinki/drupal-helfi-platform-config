<?php

declare(strict_types=1);

namespace Drupal\helfi_etusivu_entities\Plugin\ExternalEntities\StorageClient;

use Drupal\Core\Language\LanguageInterface;

/**
 * External entity storage client for News feed entities.
 *
 * @ExternalEntityStorageClient(
 *   id = "helfi_announcements",
 *   label = @Translation("Helfi: Announcements"),
 *   description = @Translation("Retrieves announcements from helfi")
 * )
 */
final class Announcements extends EtusivuEntityBase {

  /**
   * {@inheritdoc}
   */
  public static string $customCacheTag = 'helfi_external_entity_announcement';

  /**
   * {@inheritdoc}
   */
  public function query(
    array $parameters = [],
    array $sorts = [],
    $start = NULL,
    $length = NULL,
  ) : array {
    $query = [
      'fields[node--announcements]' => 'id',
      'fields[status]' => 1,
      'filter[status][value]' => 1,
      'filter[field_publish_externally][value]' => 1,
    ];

    if ($start) {
      $query['page[offset]'] = $start;
    }

    if ($length) {
      $query['page[limit]'] = $length;
    }

    // If filter is missing language set it manually.
    if (!isset($query['filter[langcode]'])) {
      $language = $this->languageManager
        ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
        ->getId();
      $query['filter[langcode]'] = $language;
    }

    return $this->request("/node/announcement", $query, $query['filter[langcode]']);
  }

}
