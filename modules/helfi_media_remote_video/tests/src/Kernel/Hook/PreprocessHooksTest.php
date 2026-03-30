<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_media_remote_video\Kernel\Hook;

use Drupal\helfi_media_remote_video\Hook\PreprocessHooks;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\IFrameMarkup;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests PreprocessHooks preprocess logic.
 */
final class PreprocessHooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'media',
    'media_test_source',
    'field',
    'user',
    'helfi_media_remote_video',
  ];

  /**
   * Returns a configured PreprocessHooks instance.
   */
  private function getHooks(): PreprocessHooks {
    return new PreprocessHooks(\Drupal::requestStack());
  }

  /**
   * Tests the replacing of YouTube video domain.
   */
  public function testReplacingYoutubeDomain(): void {
    $iframe = '<iframe src="https://www.youtube.com/embed/abc123"></iframe>';
    $variables = [
      'media' => IFrameMarkup::create($iframe),
    ];

    $this->getHooks()->preprocessMediaOembedIframe($variables);

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

    $this->getHooks()->preprocessMediaOembedIframe($variables);

    $output = $variables['media']->__toString();

    $this->assertStringContainsString(' scrolling="no"></iframe>', $output);
    $this->assertStringContainsString('players.icareus.com/helsinkikanava', $output);
    $this->assertStringNotContainsString('youtube-nocookie.com/', $output);
  }

  /**
   * Tests that the subtitles parameter is added to Icareus HUS iframe.
   */
  public function testIcareusHusSubtitlesInjected(): void {
    $iframe = '<iframe src="https://players.icareus.com/hus/embed/vod/278391244" width="640" height="360"></iframe>';
    $variables = [
      'media' => IFrameMarkup::create($iframe),
    ];

    // Simulate the OEmbedIframeController request with a source URL containing
    // the subtitles parameter.
    $request = Request::create('/media/oembed', 'GET', [
      'url' => 'https://players.icareus.com/hus/embed/vod/278391244?subtitles=sv',
    ]);
    \Drupal::requestStack()->push($request);

    $this->getHooks()->preprocessMediaOembedIframe($variables);

    $output = $variables['media']->__toString();
    $this->assertStringContainsString('subtitles=sv', $output);
    $this->assertStringContainsString('players.icareus.com/hus/embed/vod/278391244?subtitles=sv', $output);

    \Drupal::requestStack()->pop();
  }

  /**
   * Tests that subtitles are not added when source URL has no subtitles param.
   */
  public function testIcareusHusNoSubtitlesWhenAbsent(): void {
    $iframe = '<iframe src="https://players.icareus.com/hus/embed/vod/278391244" width="640" height="360"></iframe>';
    $variables = [
      'media' => IFrameMarkup::create($iframe),
    ];

    $request = Request::create('/media/oembed', 'GET', [
      'url' => 'https://players.icareus.com/hus/embed/vod/278391244',
    ]);
    \Drupal::requestStack()->push($request);

    $this->getHooks()->preprocessMediaOembedIframe($variables);

    $output = $variables['media']->__toString();
    $this->assertStringNotContainsString('subtitles=', $output);

    \Drupal::requestStack()->pop();
  }

}
