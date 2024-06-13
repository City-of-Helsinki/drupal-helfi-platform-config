<?php

declare(strict_types=1);

namespace Drupal\helfi_etusivu_entities\Plugin\ExternalEntities\StorageClient;

use Drupal\Core\Language\LanguageInterface;

/**
 * External entity storage client for News feed entities.
 *
 * @ExternalEntityStorageClient(
 *   id = "helfi_surveys",
 *   label = @Translation("Helfi: Surveys"),
 *   description = @Translation("Retrieves surveys from helfi")
 * )
 */
final class Surveys extends EtusivuEntityBase {

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
      'filter[field_survey_all_pages][value]' => 1,
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

    return $this->request("/node/survey", $query, $query['filter[langcode]']);
  }

}
