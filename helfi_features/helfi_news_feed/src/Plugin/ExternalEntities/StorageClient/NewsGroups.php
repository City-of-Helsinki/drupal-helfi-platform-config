<?php

declare(strict_types = 1);

namespace Drupal\helfi_news_feed\Plugin\ExternalEntities\StorageClient;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\external_entities\ResponseDecoder\ResponseDecoderFactoryInterface;
use Drupal\helfi_news_feed\HelfiExternalEntityBase;

/**
 * External entity storage client for News groups taxonomy terms.
 *
 * @ExternalEntityStorageClient(
 *   id = "helfi_news_groups",
 *   label = @Translation("Helfi: News groups"),
 *   description = @Translation("Retrieves news groups taxonomy terms from Helfi")
 * )
 */
final class NewsGroups extends HelfiExternalEntityBase {

  /**
   * Query parameters.
   *
   * @var array|string[]
   */
  protected array $query = [
    'fields[taxonomy_term--news_groups]' => 'id,name,changed,langcode,status',
  ];

  /**
   * Json api endpoint for taxonomy term.
   *
   * @var string
   */
  private string $endpoint = '/jsonapi/taxonomy_term/news_group';

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
    foreach ($data as $value) {
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
