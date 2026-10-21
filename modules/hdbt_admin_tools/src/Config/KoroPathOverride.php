<?php

declare(strict_types=1);

namespace Drupal\hdbt_admin_tools\Config;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorableConfigBase;
use Drupal\Core\Config\StorageInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Override the site wide koro on configured paths.
 */
final class KoroPathOverride implements ConfigFactoryOverrideInterface {

  /**
   * The appearance settings configuration.
   */
  public const string CONFIG_NAME = 'hdbt_admin_tools.site_settings';

  /**
   * The koro of the current path.
   */
  private string|false|null $koro = NULL;

  public function __construct(
    private readonly StorageInterface $configStorage,
    private readonly RequestStack $requestStack,
  ) {
  }

  /**
   * {@inheritdoc}
   *
   * @param string[] $names
   *   The config names.
   *
   * @return array<string, mixed>
   *   The config overrides.
   */
  public function loadOverrides($names): array {
    if (!in_array(self::CONFIG_NAME, $names, TRUE)) {
      return [];
    }
    $koro = $this->getKoro();

    if (!$koro) {
      return [];
    }
    return [self::CONFIG_NAME => ['site_settings' => ['koro' => $koro]]];
  }

  /**
   * Get the koro for the current request path.
   *
   * @return string|null
   *   The koro or null when no rule matches.
   */
  public function getKoro(): ?string {
    if ($this->koro !== NULL) {
      return $this->koro ?: NULL;
    }
    $this->koro = FALSE;
    $request = $this->requestStack->getCurrentRequest();

    if (!$request) {
      return NULL;
    }
    $path = $this->stripPrefixes(mb_strtolower($request->getPathInfo()));

    // Read the raw configuration to avoid recursion through config factory.
    $overrides = $this->configStorage->read(self::CONFIG_NAME)['koro_overrides'] ?? [];

    $match = NULL;

    foreach ($overrides as $override) {
      $pattern = rtrim(mb_strtolower($override['path']), '/');

      // Match the configured path and everything under it.
      if ($path !== $pattern && !str_starts_with($path, $pattern . '/')) {
        continue;
      }

      // Use the most specific rule.
      if ($match === NULL || strlen($pattern) > strlen($match)) {
        $match = $pattern;
        $this->koro = $override['koro'];
      }
    }
    return $this->koro ?: NULL;
  }

  /**
   * Remove the language and site prefixes from the path.
   *
   * @param string $path
   *   The requested path.
   *
   * @return string
   *   The path without the prefixes.
   */
  private function stripPrefixes(string $path): string {
    $languagePrefixes = ($this->configStorage->read('language.negotiation') ?: [])['url']['prefixes'] ?? [];
    $sitePrefixes = ($this->configStorage->read('helfi_proxy.settings') ?: [])['prefixes'] ?? [];
    $segments = array_filter(explode('/', $path), 'strlen');

    foreach ([$languagePrefixes, $sitePrefixes] as $prefixes) {
      if ($segments && in_array(reset($segments), array_filter($prefixes), TRUE)) {
        array_shift($segments);
      }
    }
    return rtrim('/' . implode('/', $segments), '/');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheSuffix(): string {
    return 'hdbt_admin_tools_koro_path';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($name): CacheableMetadata {
    $metadata = new CacheableMetadata();

    if ($name === self::CONFIG_NAME) {
      $metadata
        ->addCacheContexts(['url.path'])
        ->addCacheTags(['config:' . self::CONFIG_NAME]);
    }
    return $metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function createConfigObject($name, $collection = StorageInterface::DEFAULT_COLLECTION): ?StorableConfigBase {
    return NULL;
  }

}
