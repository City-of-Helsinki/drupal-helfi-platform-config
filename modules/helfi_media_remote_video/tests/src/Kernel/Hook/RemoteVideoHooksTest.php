<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_media_remote_video\Kernel\Hook;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\helfi_media_remote_video\Hook\RemoteVideoHooks;
use Drupal\helfi_media_remote_video\TerveyskylaUrlResolver;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\MediaInterface;
use Drupal\media\OEmbed\Provider;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests RemoteVideoHooks oEmbed alter with mock HTTP responses.
 */
#[Group('helfi_media_remote_video')]
#[RunTestsInSeparateProcesses]
final class RemoteVideoHooksTest extends KernelTestBase {

  use ApiTestTrait;

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
   * Tests that a Terveyskylä URL is resolved via HTTP redirect.
   */
  public function testOembedAlterResolvesTerveyskylaUrl(): void {
    $resolvedUrl = 'https://players.icareus.com/hus/embed/vod/278391244?subtitles=sv';

    $this->setupMockHttpClient([
      new Response(301, ['Location' => $resolvedUrl]),
    ]);

    $parsed_url = [
      'path' => 'https://suite.icareus.com/api/oembed',
      'query' => [
        'url' => 'https://urn.terveyskyla.fi/media/1395?lang=sv',
        'maxwidth' => 1264,
      ],
      'fragment' => '',
    ];

    $hooks = $this->container->get(RemoteVideoHooks::class);
    $hooks->oembedResourceUrlAlter($parsed_url, new Provider('Terveyskyla', 'https://terveyskyla.fi', [['url' => 'https://suite.icareus.com/api/oembed']]));

    $this->assertSame($resolvedUrl, $parsed_url['query']['url']);
  }

  /**
   * Tests that a non-Terveyskylä URL is untouched.
   */
  public function testOembedAlterSkipsNonTerveyskylaUrl(): void {
    $parsed_url = [
      'path' => 'https://www.youtube.com/oembed',
      'query' => ['url' => 'https://www.youtube.com/watch?v=abc123'],
      'fragment' => '',
    ];

    $hooks = $this->container->get(RemoteVideoHooks::class);
    $hooks->oembedResourceUrlAlter($parsed_url, new Provider('YouTube', 'https://www.youtube.com', [['url' => 'https://www.youtube.com/oembed']]));

    $this->assertSame('https://www.youtube.com/watch?v=abc123', $parsed_url['query']['url']);
  }

  /**
   * Tests that resolution failure leaves URL unchanged.
   */
  public function testOembedAlterLeavesUrlOnResolutionFailure(): void {
    // Return 200 with no Location header (not a redirect).
    $this->setupMockHttpClient([
      new Response(200),
    ]);

    $originalUrl = 'https://urn.terveyskyla.fi/media/1395?lang=sv';
    $parsed_url = [
      'path' => 'https://suite.icareus.com/api/oembed',
      'query' => ['url' => $originalUrl],
      'fragment' => '',
    ];

    $hooks = $this->container->get(RemoteVideoHooks::class);
    $hooks->oembedResourceUrlAlter($parsed_url, new Provider('Terveyskyla', 'https://terveyskyla.fi', [['url' => 'https://suite.icareus.com/api/oembed']]));

    $this->assertSame($originalUrl, $parsed_url['query']['url']);
  }

  /**
   * Tests entity update hook for remote video media.
   */
  public function testEntityUpdate(): void {
    $cacheTagsInvalidator = $this->prophesize(CacheTagsInvalidatorInterface::class);
    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $terveyskylaResolver = $this->prophesize(TerveyskylaUrlResolver::class);
    $hooks = new RemoteVideoHooks(
      $entityTypeManager->reveal(),
      $cacheTagsInvalidator->reveal(),
      $terveyskylaResolver->reveal(),
      \Drupal::requestStack(),
    );

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
