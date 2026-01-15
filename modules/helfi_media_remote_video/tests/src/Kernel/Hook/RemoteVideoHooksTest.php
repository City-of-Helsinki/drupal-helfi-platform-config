<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_media_remote_video\Kernel\Hook;

use Drupal\KernelTests\KernelTestBase;
use Drupal\media\IFrameMarkup;
use Drupal\helfi_media_remote_video\Hook\RemoteVideoHooks;

/**
 * Tests RemoteVideoHooks preprocess logic.
 */
final class RemoteVideoHooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'media',
    'helfi_media_remote_video',
  ];

  /**
   * Tests the replacing of YouTube video domain.
   */
  public function testReplacingYoutubeDomain(): void {
    $iframe = '<iframe src="https://www.youtube.com/embed/abc123"></iframe>';
    $variables = [
      'media' => IFrameMarkup::create($iframe),
    ];

    RemoteVideoHooks::preprocessMediaOembedIframe($variables);

    $output = $variables['media']->__toString();

    $this->assertStringContainsString(' scrolling="no"></iframe>', $output);
    $this->assertStringContainsString('youtube-nocookie.com/', $output);
    $this->assertStringNotContainsString('youtube.com/', $output);
  }

  /**
   * Tests adding of the scrolling=no attribute.
   */
  public function testAddingScrollingAttribute(): void {
    $iframe = '<iframe src="https://players.icareus.com/helsinkikanava/embed/vod/123456789"></iframe>';
    $variables = [
      'media' => IFrameMarkup::create($iframe),
    ];

    RemoteVideoHooks::preprocessMediaOembedIframe($variables);

    $output = $variables['media']->__toString();

    $this->assertStringContainsString(' scrolling="no"></iframe>', $output);
    $this->assertStringContainsString('players.icareus.com/helsinkikanava', $output);
    $this->assertStringNotContainsString('youtube-nocookie.com/', $output);
  }

}
