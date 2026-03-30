<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_media_remote_video\Kernel\Hook;

use Drupal\KernelTests\KernelTestBase;
use Drupal\media\MediaInterface;
use Drupal\helfi_media_remote_video\Hook\RemoteVideoHooks;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Tests RemoteVideoHooks logic.
 */
final class RemoteVideoHooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'media',
    'media_test_source',
    'paragraphs',
    'field',
    'user',
    'helfi_media_remote_video',
  ];

  /**
   * Tests entity update hook for remote video media.
   */
  public function testEntityUpdate(): void {
    // Mock cache tags invalidator and entity type manager. Create a remote
    // video hooks instance with the mocked classes.
    $cacheTagsInvalidator = $this->prophesize(CacheTagsInvalidatorInterface::class);
    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $hooks = new RemoteVideoHooks($entityTypeManager->reveal(), $cacheTagsInvalidator->reveal());

    // Non-media entity should return early.
    $nonMediaEntity = $this->prophesize(EntityInterface::class);
    $hooks->entityUpdate($nonMediaEntity->reveal());

    // Media entity with wrong bundle should return early.
    $mediaEntity = $this->prophesize(MediaInterface::class);
    $mediaEntity->bundle()->willReturn('image');
    $hooks->entityUpdate($mediaEntity->reveal());

    // Remote video media with no referencing paragraphs should return early.
    $remoteVideoMedia = $this->prophesize(MediaInterface::class);
    $remoteVideoMedia->bundle()->willReturn('remote_video');
    $remoteVideoMedia->id()->willReturn(123);

    $paragraphStorage = $this->prophesize(EntityStorageInterface::class);
    $query = $this->prophesize(QueryInterface::class);

    $entityTypeManager->getStorage('paragraph')->willReturn($paragraphStorage->reveal());
    $paragraphStorage->getQuery()->willReturn($query->reveal());
    $query->accessCheck(FALSE)->willReturn($query->reveal());
    $query->condition('field_remote_video.target_id', 123)->willReturn($query->reveal());
    $query->execute()->willReturn([]);

    $hooks->entityUpdate($remoteVideoMedia->reveal());

    // Remote video with referencing paragraphs should invalidate cache tags.
    $paragraph1 = $this->prophesize(ParagraphInterface::class);
    $paragraph1->getCacheTags()->willReturn(['paragraph:1', 'node:5']);

    $paragraph2 = $this->prophesize(ParagraphInterface::class);
    $paragraph2->getCacheTags()->willReturn(['paragraph:2', 'node:6']);

    $query->execute()->willReturn([1, 2]);
    $paragraphStorage->loadMultiple([1, 2])->willReturn([$paragraph1->reveal(), $paragraph2->reveal()]);

    // Expect cache tags to be invalidated.
    $cacheTagsInvalidator->invalidateTags(['paragraph:1', 'node:5', 'paragraph:2', 'node:6'])
      ->shouldBeCalled();

    $hooks->entityUpdate($remoteVideoMedia->reveal());
  }

}
