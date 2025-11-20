<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config;

use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\search_api\Entity\Index as SearchApiIndex;

/**
 * The multisite search helper service.
 */
final class MultisiteSearch {

  const PREFIX_SUFFIX = 'site_';
  const PREFIX_SEPARATOR = '/';

  /**
   * Service constructor.
   *
   * @param \Drupal\helfi_api_base\Environment\EnvironmentResolverInterface $environmentResolver
   *   The environment resolver.
   */
  public function __construct(
    private readonly EnvironmentResolverInterface $environmentResolver,
  ) {
  }

  /**
   * Check if index is multisite.
   *
   * @param string $index
   *   The index name.
   *
   * @return bool
   *   True if index is multisite, false otherwise.
   */
  public function isMultisiteIndex(string $index): bool {
    $indexEntity = SearchApiIndex::load($index);
    return (bool) $indexEntity && $indexEntity->getOption('helfi_platform_config_multisite');
  }

  /**
   * Get instance specific index prefix.
   *
   * @return string|null
   *   The instance specific index prefix, or NULL if not found.
   */
  public function getInstanceIndexPrefix(): ?string {
    $prefix = NULL;

    try {
      $project = $this->environmentResolver->getActiveProject();
      $prefix = $project->getName();
    }
    catch (\InvalidArgumentException) {
      // No project found, so no prefix.
      $prefix = NULL;
    }

    return $prefix ? self::PREFIX_SUFFIX . $prefix . self::PREFIX_SEPARATOR : NULL;
  }

  /**
   * Check if id string has current instance specific prefix.
   *
   * @param string $id
   *   The id string.
   *
   * @return bool
   *   True if id string has current instance specific prefix, false otherwise.
   */
  public function hasCurrentInstancePrefix(string $id): bool {
    $prefix = $this->getInstanceIndexPrefix();
    return $prefix && strpos($id, $prefix) === 0;
  }

  /**
   * Check if id has any instance specific prefix.
   *
   * @param string $id
   *   The id string.
   *
   * @return bool
   *   True if id has any instance specific prefix, false otherwise.
   */
  public function hasAnyInstancePrefix(string $id): bool {
    return preg_match('/^' . self::PREFIX_SUFFIX . '\S+' . preg_quote(self::PREFIX_SEPARATOR, '/') . '/', $id) === 1;
  }

  /**
   * Add prefix to id.
   */
  public function addPrefixToId(string $id): string {
    // Bail if id already has any instance specific prefix.
    if ($this->hasAnyInstancePrefix($id)) {
      return $id;
    }

    $prefix = $this->getInstanceIndexPrefix();
    return $prefix ? $prefix . $id : $id;
  }

}
