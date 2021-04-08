<?php

namespace Drupal\helfi_platform_config\TwigExtension;

use Drupal\image\Entity\ImageStyle;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Custom Twig extension to enable Imagecache External.
 */
class TwigExtension extends AbstractExtension {

  /**
   * {@inheritdoc}
   */
  public function getFilters() {
    return [
      new TwigFilter('helfi_imagecache_external', [
        $this,
        'imageCacheExternal',
      ]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'helfi_platform_config.twig.extension';
  }

  /**
   * Returns the URL of this image derivative for an original image path or URI.
   *
   * Example:
   *
   * @code
   *  {{ 'https://my.web.site/my-image.jpg'|helfi_imagecache_external('thumbnail') }}
   * @endcode
   *
   * @param string $path
   *   The path or URI to the original image.
   * @param string $style
   *   The image style.
   *
   * @return string|null
   *   The absolute URL where a style image can be downloaded, suitable for use
   *   in an <img> tag. Requesting the URL will cause the image to be created.
   */
  public function imageCacheExternal($path, $style) {
    // Remove hard-dependency to imagecache_external module due to
    // schema errors in tests.
    // @see https://www.drupal.org/project/imagecache_external/issues/3046230.
    if (!function_exists('imagecache_external_generate_path')) {
      trigger_error('Imagecache external module is not enabled.');
      return;
    }
    $local_path = imagecache_external_generate_path($path);

    if (!$image_style = ImageStyle::load($style)) {
      trigger_error(sprintf('Could not load image style %s.', $style));
      return;
    }

    if (!$image_style->supportsUri($local_path)) {
      trigger_error(sprintf('Could not apply image style %s.', $style));
      return;
    }

    return $image_style->buildUrl($local_path);
  }

}
