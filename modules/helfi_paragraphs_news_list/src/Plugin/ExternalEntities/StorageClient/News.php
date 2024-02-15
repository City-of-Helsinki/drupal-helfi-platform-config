<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_news_list\Plugin\ExternalEntities\StorageClient;

use Drupal\helfi_paragraphs_news_list\HelfiExternalEntityBase;

/**
 * External entity storage client for News feed entities.
 *
 * @ExternalEntityStorageClient(
 *   id = "helfi_news",
 *   label = @Translation("Helfi: News"),
 *   description = @Translation("Retrieves 'news' content from Helfi")
 * )
 */
final class News extends HelfiExternalEntityBase {

  /**
   * {@inheritdoc}
   */
  protected string $endpoint = '/jsonapi/node/news';

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $ids = NULL) : array {
    $query = [
      'filter[id][operator]' => 'IN',
      // Include tags, neighbourhoods and groups fields.
      'include' => 'tags,groups,neighbourhoods',
    ];

    foreach ($ids ?? [] as $index => $id) {
      $query[sprintf('filter[id][value][%d]', $index)] = $this
        ->prepareQueryParameter('id', $id);
    }
    $data = $this->request($query);

    // The $ids are passed in correct order, but the external data is not
    // in same order. Sort data by given $ids.
    usort($data, function (array $a, array $b) use ($ids) {
      return array_search($a['id'], $ids) - array_search($b['id'], $ids);
    });

    return $data;
  }

  /**
   * Creates a JSON:API filter for given term field.
   *
   * @param string $name
   *   The field name.
   * @param array $terms
   *   The terms.
   *
   * @return string[]
   *   The filter.
   */
  private function createTermFilter(string $name, array $terms) : array {
    if (!$terms) {
      return [];
    }
    $query = [
      sprintf('filter[taxonomy_term--news_%s][condition][operator]', $name) => 'IN',
    ];
    $query[sprintf('filter[taxonomy_term--news_%s][condition][path]', $name)] = $name === 'tags'
      ? "field_news_item_$name.id"
      : "field_news_$name.id";

    // Filter by multiple terms using 'OR' condition.
    foreach ($terms as $key => $value) {
      $query[sprintf('filter[taxonomy_term--news_%s][condition][value][%d]', $name, $key)] = $this
        ->prepareQueryParameter('id', $value['target_id']);
    }
    return $query;
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
    $query = [
      // We only care about basic entity data here.
      'fields[node--news_item]' => 'id',
      // No need to fetch non-published entities.
      'fields[status]' => 1,
      'filter[status][value]' => 1,
    ];

    if ($start) {
      $query['page[offset]'] = $start;
    }

    if ($length) {
      $query['page[limit]'] = $length;
    }

    // Map query fields to JSON:API fields.
    // @todo Document these fields.
    foreach ($parameters as $param) {
      ['field' => $field, 'value' => $value, 'operator' => $op] = $param;

      $match = match($field) {
        'langcode' => function (string $value, ?string $op): array {
          return ['filter[langcode]' => $value];
        },
        'tags' => function (array $terms, ?string $op): array {
          return $this->createTermFilter('tags', $terms);
        },
        'groups' => function (array $terms, ?string $op) : array {
          return $this->createTermFilter('groups', $terms);
        },
        'neighbourhoods' => function (array $terms, ?string $op) : array {
          return $this->createTermFilter('neighbourhoods', $terms);
        }
      };
      try {
        $query += $match($value, $op);
      }
      catch (\UnhandledMatchError) {
      }
    }

    // Map sort fields to JSON:API fields.
    // @todo Document these fields.
    foreach ($sorts as $sort) {
      ['field' => $field, 'direction' => $direction] = $sort;
      $match = match ($field) {
        'published_at' => function (string $direction) : array {
          return [
            'sort[published_at][path]' => 'published_at',
            'sort[published_at][direction]' => $direction,
          ];
        },
      };

      try {
        $query += $match($direction);
      }
      catch (\UnhandledMatchError) {
      }
    }
    return $this->request($query);
  }

}
