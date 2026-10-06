<?php

declare(strict_types=1);

namespace Drupal\helfi_search\SimilarContent\EntityNotifications;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Core\Utility\Error;
use Drupal\helfi_platform_config\EntityNotifications\EntityNotification;
use Drupal\helfi_platform_config\EntityNotifications\EntityNotificationProviderInterface;
use Drupal\helfi_search\SimilarContent\SimilarContentFinder;
use Elastic\Elasticsearch\Exception\ElasticsearchException;
use Elastic\Transport\Exception\TransportException;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Notifies about other content that is similar to the entity.
 */
final class SimilarContentNotificationProvider implements EntityNotificationProviderInterface {

  use StringTranslationTrait;

  /**
   * How long the similarity results are cached.
   */
  private const int MAX_AGE = 300;

  public function __construct(
    private readonly SimilarContentFinder $finder,
    #[Autowire(service: 'logger.channel.helfi_search')]
    private readonly LoggerInterface $logger,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function getNotifications(ContentEntityInterface $entity, RefinableCacheableDependencyInterface $cacheability): array {
    // The chunks are regenerated when the entity changes.
    $cacheability->addCacheableDependency($entity);

    try {
      $count = count($this->finder->findSimilar($entity));
    }
    catch (ElasticsearchException | TransportException $e) {
      Error::logException($this->logger, $e, level: LogLevel::INFO);
      $cacheability->mergeCacheMaxAge(0);
      return [];
    }

    // Simple time-based cache: Re-fetch new similar content results after
    // 5 minutes.
    $cacheability->mergeCacheMaxAge(self::MAX_AGE);

    if ($count === 0) {
      return [];
    }

    return [
      new EntityNotification(
        $this->formatPlural($count, '1 page with similar content', '@count pages with similar content', [], ['context' => 'Dashboard']),
        Url::fromRoute('helfi_search.similar_content', [
          'entity_type' => $entity->getEntityTypeId(),
          'entity' => $entity->id(),
          'langcode' => $entity->language()->getId(),
        ], [
          'attributes' => [
            'class' => ['use-ajax'],
            'data-dialog-type' => 'modal',
            'data-dialog-options' => Json::encode(['width' => '90%']),
          ],
        ]),
      ),
    ];
  }

}
