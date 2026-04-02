<?php

declare(strict_types=1);

namespace Drupal\helfi_media_remote_video\Hook;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\helfi_media_remote_video\TerveyskylaUrlResolver;
use Drupal\media\IFrameMarkup;
use Drupal\media\MediaInterface;
use Drupal\media\OEmbed\Provider;
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
    private readonly TerveyskylaUrlResolver $terveyskylaUrlResolver,
    private readonly RequestStack $requestStack,
  ) {}

  /**
   * Implements hook_preprocess_HOOK().
   */
  #[Hook('preprocess_media_oembed_iframe')]
  public function preprocessMediaOembedIframe(array &$variables): void {
    $iframe = $variables['media']->__toString();

    // Add scrolling="no" attribute to the inner iframe.
    $iframe = str_replace(
      '></iframe>',
      ' scrolling="no"></iframe>',
      $iframe,
    );

    // Replace the iframe URL with a no-cookie version and rebuild the markup.
    // This cannot be done via the media entity itself as it only affects
    // the URL which is sent to YouTube Oembed API.
    // See: https://www.drupal.org/i/3043821.
    if (str_contains($iframe, 'youtube.com')) {
      $iframe = str_replace(
        'youtube.com/',
        'youtube-nocookie.com/',
        $iframe,
      );
    }

    // For Icareus HUS (Terveyskylä) videos, ensure the subtitles parameter
    // from the source URL is preserved in the embed iframe. The oEmbed API
    // does not include it in the returned HTML.
    if (str_contains($iframe, 'players.icareus.com/hus/')) {
      if ($source_url = $this->requestStack->getCurrentRequest()->query->get('url')) {
        if ($query = parse_url($source_url, PHP_URL_QUERY)) {
          parse_str($query, $params);
          // Support both old content (Icareus URLs with ?subtitles=sv)
          // and new content (Terveyskylä URIs with ?lang=sv).
          $subtitlesValue = $params['subtitles'] ?? $params['lang'] ?? NULL;
          if (!empty($subtitlesValue)) {
            // Encode to prevent injection of HTML.
            $subtitles = htmlentities(urlencode($subtitlesValue));

            // Inject the subtitles query parameter into the iframe URL.
            $iframe = preg_replace_callback(
              '/(src=["\'])([^"\']*players\.icareus\.com\/hus\/embed\/vod\/[^"\']*)/',
              static fn(array $m): string => $m[1] . $m[2] . (str_contains($m[2], '?') ? '&' : '?') . 'subtitles=' . $subtitles,
              $iframe,
            );
          }
        }
      }
    }

    $variables['media'] = IFrameMarkup::create($iframe);
  }

  /**
   * Implements hook_oembed_resource_url_alter().
   *
   * Resolves Terveyskylä URN URLs to actual player embed URLs before the
   * oEmbed resource is fetched. This allows storing the stable URN in the
   * database while resolving to the current provider at render time.
   */
  #[Hook('oembed_resource_url_alter')]
  public function oembedResourceUrlAlter(array &$parsed_url, Provider $provider): void {
    if ($provider->getName() !== 'Terveyskyla') {
      return;
    }

    $url = $parsed_url['query']['url'] ?? '';

    if (!$this->terveyskylaUrlResolver->isTerveyskylaUrl($url)) {
      return;
    }

    $resolved = $this->terveyskylaUrlResolver->resolve($url);

    if ($resolved === NULL) {
      return;
    }

    $parsed_url['query']['url'] = $resolved;
  }

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
