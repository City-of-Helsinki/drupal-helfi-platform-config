<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_media_remote_video\Unit\Hook;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\helfi_media_remote_video\Hook\RemoteVideoHooks;
use Drupal\helfi_media_remote_video\TerveyskylaUrlResolver;
use Drupal\media\IFrameMarkup;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests RemoteVideoHooks preprocess logic.
 *
 * @group helfi_media_remote_video
 */
final class RemoteVideoHooksTest extends TestCase {

  /**
   * Creates a RemoteVideoHooks instance with the given request.
   */
  private function createHooks(?Request $request = NULL): RemoteVideoHooks {
    $requestStack = new RequestStack();
    if ($request) {
      $requestStack->push($request);
    }

    return new RemoteVideoHooks(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(CacheTagsInvalidatorInterface::class),
      $this->createMock(TerveyskylaUrlResolver::class),
      $requestStack,
    );
  }

  /**
   * Data provider for iframe preprocessing tests.
   */
  public static function iframeDataProvider(): array {
    return [
      'YouTube domain replaced with nocookie' => [
        'iframe' => '<iframe src="https://www.youtube.com/embed/abc123"></iframe>',
        'requestUrl' => NULL,
        'expectedStrings' => ['scrolling="no"', 'youtube-nocookie.com/'],
        'unexpectedStrings' => ['youtube.com/'],
      ],
      'scrolling=no added to Icareus iframe' => [
        'iframe' => '<iframe src="https://players.icareus.com/helsinkikanava/embed/vod/123456789"></iframe>',
        'requestUrl' => NULL,
        'expectedStrings' => ['scrolling="no"', 'players.icareus.com/helsinkikanava'],
        'unexpectedStrings' => ['youtube-nocookie.com/'],
      ],
      'subtitles injected from Icareus URL' => [
        'iframe' => '<iframe src="https://players.icareus.com/hus/embed/vod/278391244" width="640" height="360"></iframe>',
        'requestUrl' => 'https://players.icareus.com/hus/embed/vod/278391244?subtitles=sv',
        'expectedStrings' => ['players.icareus.com/hus/embed/vod/278391244?subtitles=sv'],
        'unexpectedStrings' => [],
      ],
      'subtitles injected from Terveyskylä lang param' => [
        'iframe' => '<iframe src="https://players.icareus.com/hus/embed/vod/278391244" width="640" height="360"></iframe>',
        'requestUrl' => 'https://urn.terveyskyla.fi/media/1395?lang=sv',
        'expectedStrings' => ['players.icareus.com/hus/embed/vod/278391244?subtitles=sv'],
        'unexpectedStrings' => [],
      ],
      'no subtitles when Terveyskylä URL has no lang' => [
        'iframe' => '<iframe src="https://players.icareus.com/hus/embed/vod/278391244" width="640" height="360"></iframe>',
        'requestUrl' => 'https://urn.terveyskyla.fi/media/1395',
        'expectedStrings' => [],
        'unexpectedStrings' => ['subtitles='],
      ],
      'no subtitles when Icareus URL has no subtitles param' => [
        'iframe' => '<iframe src="https://players.icareus.com/hus/embed/vod/278391244" width="640" height="360"></iframe>',
        'requestUrl' => 'https://players.icareus.com/hus/embed/vod/278391244',
        'expectedStrings' => [],
        'unexpectedStrings' => ['subtitles='],
      ],
    ];
  }

  /**
   * Tests iframe preprocessing.
   */
  #[DataProvider('iframeDataProvider')]
  public function testPreprocessMediaOembedIframe(string $iframe, ?string $requestUrl, array $expectedStrings, array $unexpectedStrings): void {
    $variables = [
      'media' => IFrameMarkup::create($iframe),
    ];

    $request = $requestUrl
      ? Request::create('/media/oembed', 'GET', ['url' => $requestUrl])
      : NULL;

    $this->createHooks($request)->preprocessMediaOembedIframe($variables);

    $output = $variables['media']->__toString();

    foreach ($expectedStrings as $expected) {
      $this->assertStringContainsString($expected, $output);
    }
    foreach ($unexpectedStrings as $unexpected) {
      $this->assertStringNotContainsString($unexpected, $output);
    }
  }

}
