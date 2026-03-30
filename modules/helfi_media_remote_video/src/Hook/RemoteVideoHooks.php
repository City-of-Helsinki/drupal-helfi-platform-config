<?php

declare(strict_types=1);

namespace Drupal\helfi_media_remote_video\Hook;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\media\IFrameMarkup;
use Drupal\media\MediaInterface;
use Drupal\paragraphs\ParagraphInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Hooks for helfi_media_remote_video module.
 */
class RemoteVideoHooks {

  use AutowireTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
  ) {}

  /**
   * Implements hook_entity_update().
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    if (
      !$entity instanceof MediaInterface ||
      $entity->bundle() !== 'remote_video'
    ) {
      return;
    }

    $paragraphs = $this->getReferencingParagraphs($entity);

    if ($paragraphs === []) {
      return;
    }

    // Collect cache tags from all referencing paragraphs.
    $cacheTags = array_reduce(
      $paragraphs,
      static function (array $tags, ParagraphInterface $paragraph): array {
        return Cache::mergeTags($tags, $paragraph->getCacheTags());
      },
      [],
    );

    if ($cacheTags === []) {
      return;
    }

    $this->cacheTagsInvalidator->invalidateTags($cacheTags);
  }

  /**
   * Gets paragraphs that reference the media entity.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   *
   * @return \Drupal\paragraphs\ParagraphInterface[]
   *   The referencing paragraphs.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getReferencingParagraphs(MediaInterface $media): array {
    $storage = $this->entityTypeManager->getStorage('paragraph');

    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('field_remote_video.target_id', $media->id())
      ->execute();

    if ($ids === []) {
      return [];
    }

    $paragraphs = $storage->loadMultiple($ids);

    return array_filter(
      $paragraphs,
      static fn (mixed $paragraph): bool => $paragraph instanceof ParagraphInterface,
    );
  }

}
