<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_media_remote_video\Kernel\Entity;

use Drupal\helfi_media_remote_video\Entity\RemoteVideo;
use Drupal\media\Controller\OEmbedIframeController;
use Drupal\media\Entity\Media;
use Drupal\media\OEmbed\Resource;
use Drupal\media\OEmbed\ResourceException;
use Drupal\media\OEmbed\ResourceFetcherInterface;
use Drupal\media\Plugin\Validation\Constraint\OEmbedResourceConstraint;
use Drupal\media\Plugin\Validation\Constraint\OEmbedResourceConstraintValidator;
use Drupal\Tests\helfi_media\Kernel\HelfiMediaKernelTestBase;
use Drupal\media\OEmbed\Provider;
use Drupal\media\OEmbed\UrlResolverInterface;
use Drupal\Tests\media\Traits\OEmbedTestTrait;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Tests RemoteVideo entity bundle class.
 *
 * @group helfi_media_remote_video
 */
class RemoteVideoTest extends HelfiMediaKernelTestBase {

  use ProphecyTrait;
  use OEmbedTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_api_base',
    'helfi_media_remote_video',
    'oembed_providers',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig([
      'helfi_media_remote_video',
    ]);
  }

  /**
   * Test data for remote video bundle class.
   */
  protected function remoteVideoDataProvider() : array {
    return [
      'nonexistent' => [
        'name' => 'nonexistent',
        'type' => 'invalid',
        'url' => '',
        'title' => '',
        'service_url' => '',
        'provider' => '',
        'embed_url' => '',
      ],
      'invalid_youtube' => [
        'name' => 'youtube',
        'type' => 'invalid',
        'url' => 'https://www.youtube.com/random-id',
        'title' => 'Invalid: Youtube video',
        'service_url' => 'https://www.youtube.com',
        'provider' => 'Youtube',
        'embed_url' => 'https://www.youtube.com/embed/random-id',
      ],
      'invalid_icareus_suite' => [
        'name' => 'icareus_suite',
        'type' => 'invalid',
        'url' => 'https://www.helsinkikanava.fi/fi/web/helsinkikanava/player?assetId=123456',
        'title' => 'Invalid: Helsinki kanava video',
        'service_url' => 'https://www.helsinkikanava.fi',
        'provider' => 'Icareus Suite',
        'embed_url' => 'https://suite.icareus.com/api/oembed/123456',
      ],
      'youtube' => [
        'name' => 'youtube',
        'type' => 'valid',
        'url' => 'https://www.youtube.com/watch?v=random-id',
        'title' => 'Youtube video',
        'service_url' => 'https://www.youtube.com',
        'provider' => 'Youtube',
        'embed_url' => 'https://www.youtube.com/embed/random-id',
      ],
      'icareus_suite' => [
        'name' => 'icareus_suite',
        'type' => 'valid',
        'url' => 'https://www.helsinkikanava.fi/fi/web/helsinkikanava/player/vod?assetId=123456',
        'title' => 'Helsinki kanava video',
        'service_url' => 'https://www.helsinkikanava.fi',
        'provider' => 'Icareus Suite',
        'embed_url' => 'https://suite.icareus.com/api/oembed/123456',
      ],
    ];
  }

  /**
   * Tests remote video bundle class.
   *
   * @param string $name
   *   The remote video name.
   * @param string $type
   *   The test type.
   * @param string $url
   *   The remote video URL.
   * @param string $title
   *   The remote video title.
   * @param string $service_url
   *   The remote video service URL.
   * @param string $provider
   *   The remote video provider.
   * @param string $embed_url
   *   The remote video embed URL.
   *
   * @throws \Drupal\media\OEmbed\ProviderException
   * @throws \Drupal\media\OEmbed\ResourceException
   *
   * @dataProvider remoteVideoDataProvider
   */
  public function testRemoteVideoBundle(string $name, string $type, string $url, string $title, string $service_url, string $provider, string $embed_url): void {
    // Default values.
    $max_width = 1280;
    $max_height = 720;

    // Mock oEmbed provider, resource fetcher and Oembed URL resolver.
    $oembed_provider = $this->prophesize(Provider::class);
    $resource_fetcher = $this->prophesize(ResourceFetcherInterface::class);
    $url_resolver = $this->prophesize(UrlResolverInterface::class);

    // Set up the mock to return the value what a real provider would return.
    $oembed_provider->getUrl()->willReturn("$service_url/");
    $resource_fetcher->fetchResource($url)->willReturn($this->createResource([
      'html' => "<iframe width=\"$max_width\" height=\"$max_height\" src=\"$embed_url\"></iframe>",
      'title' => $name,
      'thumbnail_url' => '',
    ], $provider));

    // Overwrite the default resource fetcher with the mock.
    $this->container->set('media.oembed.resource_fetcher', $resource_fetcher->reveal());

    // Set up the mock to return the value what getResourceUrl would return
    // with a real endpoint url.
    $url_resolver->getResourceUrl($url, $max_width, $max_height)->willReturn($url);
    $url_resolver->getResourceUrl($url)->willReturn($url);

    // Set up the mock to return the value based on the test type.
    $type === 'valid'
      ? $url_resolver->getProviderByUrl($url)->willReturn($oembed_provider->reveal())
      : $url_resolver->getProviderByUrl($url)->willThrow(new ResourceException('No matching provider found.', $url));

    // Overwrite the default oEmbed URL resolver with the mock.
    $this->container->set('media.oembed.url_resolver', $url_resolver->reveal());

    /** @var \Drupal\helfi_media_remote_video\Entity\RemoteVideo $media */
    $media = $this->createMediaEntity([
      'name' => $name,
      'bundle' => 'remote_video',
      'field_media_oembed_video' => [
        'value' => $url,
        'iframe_title' => $title,
      ],
    ]);

    // Test the invalid values.
    if ($type === 'invalid') {
      // Prophesize the context and oEmbed URL resolver.
      $context = $this->prophesize(ExecutionContextInterface::class);
      $constraint = new OEmbedResourceConstraint();

      // When the media item has no provider, the source value will be empty.
      // The constraint validator should add a violation and return early
      // before invoking the URL resolver.
      if (empty($provider)) {
        $context->addViolation($constraint->invalidResourceMessage)->shouldBeCalled();
        $url_resolver->getProviderByUrl(Argument::any())->shouldNotBeCalled();
      }

      // Overwrite the default oEmbed constraint validator with the mock.
      $validator = new OEmbedResourceConstraintValidator(
        $url_resolver->reveal(),
        $this->container->get('media.oembed.resource_fetcher'),
        $this->container->get('logger.factory')
      );
      $validator->initialize($context->reveal());

      // When dealing with invalid values and the provider is set, the
      // constraint validator should add an unknown provider message violation.
      if (!empty($provider)) {
        $context->addViolation($constraint->unknownProviderMessage)->shouldBeCalled();
      }

      // Validate the media item.
      $validator->validate($this->getValue($media), $constraint);
      return;
    }

    // Test the non-empty media item values.
    $this->assertInstanceOf(RemoteVideo::class, $media);
    $this->assertSame($service_url, $media->getServiceUrl());

    // Test the oEmbed iframe output on YouTube and verify the no-cookie domain.
    if ($provider === 'Youtube') {
      /** @var \Drupal\media\IFrameUrlHelper $iframe_helper */
      $iframe_helper = $this->container->get('media.oembed.iframe_url_helper');

      // Generate the correct hash so the controller accepts the request.
      $hash = $iframe_helper->getHash($url, $max_width, $max_height);

      // Create a fake request to simulate accessing /media/oembed.
      $request = Request::create('/media/oembed', 'GET', [
        'url' => $url,
        'max_width' => $max_width,
        'max_height' => $max_height,
        'hash' => $hash,
      ]);

      // Create the Oembed iframe controller manually and call the render
      // method directly.
      $controller = OEmbedIframeController::create($this->container);
      $response = $controller->render($request);

      // Verify the response and check that the no-cookie domain was used.
      $this->assertEquals(200, $response->getStatusCode());
      $this->assertStringContainsString('<iframe', $response->getContent());
      $this->assertStringContainsString(
        'youtube-nocookie.com',
        $response->getContent(),
        'YouTube iframe was converted to use the no-cookie domain.'
      );
    }
  }

  /**
   * Create a mock oEmbed resource.
   *
   * @param array $data
   *   The resource data.
   * @param string $provider
   *   The provider name.
   *
   * @return \Drupal\media\OEmbed\Resource
   *   The oEmbed video resource.
   *
   * @throws \Drupal\media\OEmbed\ProviderException
   */
  protected function createResource(array $data = [], string $provider = ''): Resource {
    return Resource::video(
      html: $data['html'],
      width: '1280',
      provider: new Provider($provider, 'https://www.test.hel.ninja', [
        ['url' => 'https://www.test.hel.ninja'],
      ]),
      title: $data['title'],
      thumbnail_url: $data['thumbnail_url'],
    );
  }

  /**
   * Wrap a media entity in an anonymous class to mock its field value.
   *
   * @param \Drupal\media\Entity\Media $media
   *   The media object.
   *
   * @return object
   *   The mock field value to validate.
   */
  protected function getValue(Media $media): object {
    return new class ($media) {
      // phpcs:ignore
      private $entity;

      public function __construct($entity) {
        $this->entity = $entity;
      }

      // phpcs:ignore
      public function getEntity() {
        return $this->entity;
      }

    };
  }

}
