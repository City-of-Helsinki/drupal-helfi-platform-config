<?php

declare(strict_types = 1);

namespace Drupal\helfi_news_feed\Plugin\ExternalEntities\StorageClient;

use Drupal\Core\Language\LanguageInterface;
use Drupal\helfi_news_feed\HelfiExternalEntityBase;

/**
 * External entity storage client for News tags taxonomy terms.
 *
 * @ExternalEntityStorageClient(
 *   id = "helfi_news_tags",
 *   label = @Translation("Helfi: News tags"),
 *   description = @Translation("Retrieves news tags taxonomy terms from Helfi")
 * )
 */
final class NewsTags extends HelfiExternalEntityBase {

  /**
   * Query parameters.
   *
   * @var array|string[]
   */
  protected array $query = [
    'fields[taxonomy_term--news_tags]' => 'id,name,changed,langcode,status'
  ];

  /**
   * Json api endpoint for taxonomy term.
   *
   * @var string
   */
  private string $endpoint = '/jsonapi/taxonomy_term/news_tags';

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $ids = NULL) {
    foreach ($ids ?? [] as $index => $id) {
      $this->query[sprintf('filter[id][value][%d]', $index)] = $id;
    }
    $contains = isset($parameters[0]) ? $parameters[0]['value'] : '';
    if ($contains) {
      $this->query['filter[name-filter][condition][path]'] = 'name';
      $this->query['filter[name-filter][condition][operator]'] = 'CONTAINS';
      $this->query['filter[name-filter][condition][value]'] = $contains;
    }

    $data = $this->request($this->endpoint, $this->query);
    $prepared = [];
    foreach ($data as $key => $value) {
      $prepared[$value["id"]] = $value;
    }

    return $prepared;
  }

  /**
   * {@inheritdoc}
   */
  public function query(
    array $parameters = [],
    array $sorts = [],
          $start = NULL,
          $length = NULL
  ) : array {
    $contains = isset($parameters[0]) ? $parameters[0]['value'] : '';
    if ($contains) {
      $this->query['filter[name-filter][condition][path]'] = 'name';
      $this->query['filter[name-filter][condition][operator]'] = 'CONTAINS';
      $this->query['filter[name-filter][condition][value]'] = $contains;
    }

    $data = $this->request($this->endpoint, $this->query);
    $prepared = [];
    foreach ($data as $value) {
      $prepared[$value["id"]] = $value;
    }

    return $prepared;
  }

}
