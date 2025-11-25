<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config;

use Drupal\Core\Asset\JsOptimizer;
use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The Javscript inliner service.
 */
class JsInliner {

  /**
   * Fast storage for multiple calls within the same request.
   */
  private array $store;

  /**
   * Constructs a new JsInliner object.
   */
  public function __construct(
    #[Autowire(service: 'asset.js.optimizer')]
    private JsOptimizer $jsOptimizer,
    private LibraryDiscoveryInterface $libraryDiscovery,
    #[Autowire(service: 'cache.default')]
    private CacheBackendInterface $cache
  ) {
  }

  /**
   * Reset internal storage.
   */
  public function reset(): void {
    $this->store = [];
  }

  /**
   * Get the inline javascript for a given library.
   *
   * @param string $extension
   *   The extension name. Usually the module or theme name.
   * @param string $name
   *   The library name.
   *
   * @return string|null
   */
  public function getInline(string $extension, string $name): ?string {
    // Store for multiple calls within the same request.
    if (isset($this->store[$extension][$name])) {
      return $this->store[$extension][$name];
    }

    try {
      $library = $this->libraryDiscovery->getLibraryByName($extension, $name);
    }
    catch (\Exception $e) {
      $library = FALSE;
    }

    if (!$library || empty($library['js'])) {
      return NULL;
    }

    $version = $library['version'] ?? '0';
    $cid = "helfi_platform_config:inline_js:$extension.$name:$version";
    $cache = $this->cache->get($cid);
    if ($cache) {
      $this->store[$extension][$name] = $cache->data;
      return $cache->data;
    }

    $data = '';

    foreach ($library['js'] as $js_asset) {
      try {
        $data .= $this->jsOptimizer->optimize($js_asset + ['preprocess' => TRUE]);
      }
      catch (\Exception $e) {
        continue;
      }
    }

    $this->store[$extension][$name] = $data;
    $this->cache->set($cid, $data);

    return $data;
  }

}
