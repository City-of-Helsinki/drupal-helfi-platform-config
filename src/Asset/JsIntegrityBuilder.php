<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Asset;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Asset\AssetCollectionGroupOptimizerInterface;
use Drupal\Core\Asset\AssetCollectionGrouperInterface;
use Drupal\Core\Asset\AssetGroupSetHashTrait;
use Drupal\Core\Asset\AssetResolverInterface;
use Drupal\Core\Asset\AttachedAssets;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Theme\ThemeInitializationInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\csp\Csp;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Builds Subresource Integrity metadata for JavaScript file assets.
 *
 * Lazy JS aggregates may not exist on disk during page rendering because
 * Drupal generates them on first request. In that case the hash is computed
 * from the aggregate content directly.
 *
 * @see https://www.w3.org/TR/CSP3/#external-hash
 */
class JsIntegrityBuilder {

  use AssetGroupSetHashTrait;

  /**
   * Constructs a JsIntegrityBuilder.
   */
  public function __construct(
    private FileSystemInterface $fileSystem,
    #[Autowire(service: 'cache.default')]
    private CacheBackendInterface $cache,
    #[Autowire('%app.root%')]
    private string $appRoot,
    private AssetResolverInterface $assetResolver,
    #[Autowire(service: 'asset.js.collection_grouper')]
    private AssetCollectionGrouperInterface $jsGrouper,
    #[Autowire(service: 'asset.js.collection_optimizer')]
    private AssetCollectionGroupOptimizerInterface $jsOptimizer,
    private LanguageManagerInterface $languageManager,
    private ThemeManagerInterface $themeManager,
    private ThemeInitializationInterface $themeInitialization,
  ) {
  }

  /**
   * Get SRI integrity metadata for a rendered script URL.
   *
   * @param string $script_url
   *   The script URL as rendered in a script tag.
   *
   * @return string|null
   *   Integrity metadata in the form "sha256-{base64-value}", or NULL.
   */
  public function getIntegrityForScriptUrl(string $script_url): ?string {
    $path = $this->resolveAssetPath($script_url);
    if ($path !== NULL && is_readable($path)) {
      return $this->hashReadableFile($path);
    }

    if ($this->isLazyAggregateUrl($script_url)) {
      return $this->getIntegrityForLazyAggregate($script_url);
    }

    return NULL;
  }

  /**
   * Hash the contents of a readable file, with caching.
   */
  private function hashReadableFile(string $path): ?string {
    $mtime = (int) filemtime($path);
    $cid = 'helfi_platform_config:js_integrity:' . md5($path) . ':' . $mtime;
    $cached = $this->cache->get($cid);
    if ($cached) {
      return $cached->data;
    }

    $contents = file_get_contents($path);
    if ($contents === FALSE) {
      return NULL;
    }

    $integrity = Csp::calculateHash($contents);
    $this->cache->set($cid, $integrity);

    return $integrity;
  }

  /**
   * Compute integrity for a lazy JS aggregate URL.
   */
  private function getIntegrityForLazyAggregate(string $script_url): ?string {
    $cid = 'helfi_platform_config:js_lazy_integrity:' . md5($script_url);
    $cached = $this->cache->get($cid);
    if ($cached) {
      return $cached->data;
    }

    $parsed = parse_url($script_url);
    $url_path = $parsed['path'] ?? '';
    if (!preg_match('#/js_(.+)\.js$#', $url_path, $filename_matches)) {
      return NULL;
    }
    $received_hash = $filename_matches[1];

    $query = [];
    parse_str($parsed['query'] ?? '', $query);
    foreach (['scope', 'delta', 'theme', 'language', 'include'] as $required_key) {
      if (!isset($query[$required_key])) {
        return NULL;
      }
    }

    $previous_theme = NULL;
    if ($this->themeManager->getActiveTheme()->getName() !== $query['theme']) {
      $previous_theme = $this->themeManager->getActiveTheme();
      $this->themeManager->setActiveTheme(
        $this->themeInitialization->initTheme($query['theme']),
      );
    }

    try {
      $language = $this->languageManager->getLanguage($query['language']);
      if ($language === NULL) {
        return NULL;
      }

      $attached_assets = new AttachedAssets();
      $include_libraries = explode(',', UrlHelper::uncompressQueryParameter($query['include']));
      $attached_assets->setLibraries($include_libraries);

      if (!empty($query['exclude'])) {
        $exclude_libraries = explode(',', UrlHelper::uncompressQueryParameter($query['exclude']));
        $attached_assets->setAlreadyLoadedLibraries($exclude_libraries);
      }

      [$js_assets_header, $js_assets_footer] = $this->assetResolver->getJsAssets(
        $attached_assets,
        FALSE,
        $language,
      );
      $assets = $query['scope'] === 'header' ? $js_assets_header : $js_assets_footer;
      unset($assets['drupalSettings']);

      $groups = $this->jsGrouper->group($assets);
      $delta = (int) $query['delta'];
      if (!isset($groups[$delta])) {
        return NULL;
      }

      $group = $groups[$delta];
      if (!hash_equals($this->generateHash($group), $received_hash)) {
        return NULL;
      }

      $integrity = Csp::calculateHash($this->jsOptimizer->optimizeGroup($group));
      $this->cache->set($cid, $integrity);

      return $integrity;
    }
    finally {
      if ($previous_theme !== NULL) {
        $this->themeManager->setActiveTheme($previous_theme);
      }
    }
  }

  /**
   * Check whether a script URL points to a lazy JS aggregate.
   */
  private function isLazyAggregateUrl(string $script_url): bool {
    $parsed = parse_url($script_url);
    if (!is_string($parsed['path'] ?? NULL) || !str_contains($parsed['path'], '/js/js_')) {
      return FALSE;
    }

    $query = [];
    parse_str($parsed['query'] ?? '', $query);

    return isset($query['scope'], $query['delta'], $query['include'], $query['theme'], $query['language']);
  }

  /**
   * Resolve a Drupal asset URI or generated URL to a local filesystem path.
   */
  private function resolveAssetPath(string $uri): ?string {
    $url_path = parse_url($uri, PHP_URL_PATH);
    if (is_string($url_path) && $url_path !== '') {
      if (preg_match('#^/(?:dev-)?etusivu-assets/(.+)$#', $url_path, $matches)) {
        $resolved = $this->resolveRelativePath($matches[1]);
        if ($resolved !== NULL) {
          return $resolved;
        }
      }

      if (preg_match('#/sites/default/files/js/(js_[^/]+\.js)$#', $url_path, $matches)) {
        $resolved = $this->fileSystem->realpath('assets://js/' . $matches[1]);
        if ($resolved !== FALSE) {
          return $resolved;
        }

        $resolved = $this->fileSystem->realpath('public://js/' . $matches[1]);
        if ($resolved !== FALSE) {
          return $resolved;
        }
      }

      if (preg_match('#/sites/default/files/(.+)$#', $url_path, $matches)) {
        $resolved = $this->fileSystem->realpath('public://' . $matches[1]);
        if ($resolved !== FALSE) {
          return $resolved;
        }
      }

      if (preg_match('#/assets/js/(.+\.js)$#', $url_path, $matches)) {
        $resolved = $this->fileSystem->realpath('assets://js/' . $matches[1]);
        if ($resolved !== FALSE) {
          return $resolved;
        }
      }
    }

    $uri = strtok($uri, '?') ?: $uri;

    if (!str_contains($uri, '://')) {
      return $this->resolveRelativePath($uri);
    }

    $path = $this->fileSystem->realpath($uri);

    return $path !== FALSE ? $path : NULL;
  }

  /**
   * Resolve a Drupal-relative path against the application root.
   */
  private function resolveRelativePath(string $relative_path): ?string {
    $relative_path = ltrim($relative_path, '/');
    $candidates = [
      $this->appRoot . '/' . $relative_path,
    ];

    if (!str_ends_with($this->appRoot, '/public')) {
      $candidates[] = $this->appRoot . '/public/' . $relative_path;
    }

    foreach ($candidates as $candidate) {
      $resolved = $this->fileSystem->realpath($candidate);
      if ($resolved !== FALSE && is_readable($resolved)) {
        return $resolved;
      }
    }

    return NULL;
  }

}
