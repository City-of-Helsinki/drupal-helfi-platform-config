<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_news_list\Entity;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\helfi_paragraphs_news_list\EventSubscriber\CacheResponseSubscriber;

/**
 * A lazy builder for news list.
 */
final readonly class NewsListLazyBuilder implements TrustedCallbackInterface {

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Lazy loader callback for news list.
   *
   * @param string $id
   *   The entity ID.
   *
   * @return array
   *   The render array.
   */
  public function build(string $id) : array {
    $entity = $this->entityTypeManager->getStorage('paragraph')
      ->load($id);

    if (!$entity instanceof NewsFeedParagraph) {
      return [];
    }
    $build = [];

    $storage = $this->entityTypeManager
      ->getStorage('helfi_news');

    $query = $storage
      ->getQuery();
    $query
      ->condition('search_api_language', $entity->language()->getId())
      ->range(0, $entity->getLimit());

    $termFilters = [
      'tags_uuid' => $entity->getTagsUuid(),
      'groups_uuid' => $entity->getGroupsUuid(),
      'neighbourhoods_uuid' => $entity->getNeighbourhoodsUuids(),
    ];
    foreach ($termFilters as $name => $value) {
      $query->condition($name, $value, 'IN');
    }
    $query->sort('published_at', 'DESC');

    $query->accessCheck(FALSE);

    $ids = $query->execute();
    $entities = $storage->loadMultiple($ids);

    // Don't cache empty results for longer periods of time.
    if (empty($entities)) {
      // Setting a max-age here is not enough since it would not bubble up to
      // the page cache. Therefore, we set a cache tag for empty results and let
      // CacheResponseSubscriber set the max-age of the response cache when
      // the cache tag is present.
      // @see https://www.drupal.org/node/2352009
      // @see \Drupal\helfi_paragraphs_news_list\EventSubscriber\CacheResponseSubscriber::handleNewsListCache()
      $build['#cache']['tags'][] = 'helfi_news_list_empty_results';
      $build['#cache']['max-age'] = CacheResponseSubscriber::EMPTY_LIST_MAX_AGE;
      $build['#theme'] = 'news_list__no_results';
    }

    foreach ($entities as $item) {
      $item->addCacheableDependency($entity);

      $build[] = $this->entityTypeManager
        ->getViewBuilder('helfi_news')
        ->view($item, 'medium_teaser');
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['build'];
  }

}
