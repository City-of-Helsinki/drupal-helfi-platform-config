<?php

declare(strict_types=1);

namespace Drupal\helfi_etusivu_entities\Plugin\ExternalEntities\StorageClient;

/**
 * External entity storage client for News feed entities.
 *
 * @StorageClient(
 *   id = "helfi_surveys",
 *   label = @Translation("Helfi: Surveys"),
 *   description = @Translation("Retrieves surveys from helfi")
 * )
 */
final class Surveys extends EtusivuJsonApiEntityBase {

  /**
   * Custom cache tag for announcements.
   *
   * @var string
   */
  public static string $customCacheTag = 'helfi_external_entity_survey';

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
      'fields[node--survey]' => 'id,langcode,status,published_at,unpublish_on,title,body,field_survey_link',
      'fields[status]' => 1,
      'filter[status][value]' => 1,
      'filter[field_publish_externally][value]' => 1,
      'sort' => '-published_at',
    ];

    // This only ever returns the latest survey.
    $query += $this->queryLimits($start, 1);
    $query += $this->queryDefaultLangcode();

    return $this->request("/node/survey", $query, $query['filter[langcode]']);
  }

}
