<?php

declare(strict_types=1);

namespace Drupal\helfi_etusivu_entities\Plugin\ExternalEntities\StorageClient;

/**
 * External entity storage client for Announcement-entities.
 *
 * @StorageClient(
 *   id = "helfi_announcements",
 *   label = @Translation("Helfi: Announcements"),
 *   description = @Translation("Retrieves announcements from helfi")
 * )
 */
final class Announcements extends EtusivuJsonApiEntityBase {

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

    $query += $this->queryLimits($start, $length);
    $query += $this->queryDefaultLangcode();

    return $this->request("/node/announcement", $query, $query['filter[langcode]']);
  }

  public function querySource(array $parameters = [], array $sorts = [], ?int $start = NULL, ?int $length = NULL): array {
    // @todo Implement
    return [];
  }

  public function transliterateDrupalFilters(array $parameters, array $context = []): array {
    // @todo Implement
    return [];
  }
}
