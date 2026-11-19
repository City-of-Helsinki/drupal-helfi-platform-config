<?php

declare(strict_types=1);

namespace Drupal\helfi_media\Hook;

use Drupal\Core\Cache\Cache;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\image\ImageStyleInterface;
use Drupal\media\MediaInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Adds the image viewer to image paragraphs.
 */
final class ImageViewerHooks {

  use AutowireTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Implements hook_preprocess_HOOK() for paragraph.
   *
   * @param array $variables
   *   The template variables.
   */
  #[Hook('preprocess_paragraph')]
  public function preprocessParagraph(array &$variables): void {
    $paragraph = $variables['paragraph'] ?? NULL;

    if (
      !$paragraph instanceof ParagraphInterface ||
      $paragraph->bundle() !== 'image' ||
      !$paragraph->hasField('field_image')) {
      return;
    }

    $media = $paragraph->get('field_image')->entity;
    $style = $this->entityTypeManager->getStorage('image_style')->load('orig_3840w_3840h');

    if (
      !$media instanceof MediaInterface ||
      !$style instanceof ImageStyleInterface ||
      !$media->hasField('field_media_image') ||
      $media->get('field_media_image')->isEmpty()
    ) {
      return;
    }

    $langcode = $variables['language']->getId();
    if ($media->hasTranslation($langcode)) {
      $media = $media->getTranslation($langcode);
    }

    /** @var \Drupal\image\Plugin\Field\FieldType\ImageItem $item */
    $item = $media->get('field_media_image')->first();
    $uri = $item->entity?->getFileUri();

    if (!$uri) {
      return;
    }

    $dimensions = ['width' => $item->width, 'height' => $item->height];
    $style->transformDimensions($dimensions, $uri);

    $variables['image_viewer'] = [
      'src' => $style->buildUrl($uri),
      'width' => $dimensions['width'],
      'height' => $dimensions['height'],
      'alt' => $item->alt,
    ];
    $variables['#attached']['library'][] = 'helfi_platform_config/image_viewer';
    $variables['#cache']['tags'] = Cache::mergeTags(
      $variables['#cache']['tags'] ?? [],
      $style->getCacheTags()
    );
  }

}
