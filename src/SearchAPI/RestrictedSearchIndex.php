<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\SearchAPI;

use Drupal\helfi_api_base\Environment\EnvironmentResolverException;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;

/**
 * Shared search indexes, their main instance and their site field.
 */
final class RestrictedSearchIndex {

  /**
   * Shared index ids mapped to their main instance and site field.
   *
   * The site field holds the name of the instance that indexed the document.
   *
   * @var array<string, array{main: string, site_field: string}>
   */
  private const array SHARED = [
    'embeddings' => ['main' => Project::ETUSIVU, 'site_field' => 'instance'],
    'suggestions' => ['main' => Project::ETUSIVU, 'site_field' => 'parent_instance'],
    'hyte' => ['main' => Project::ASUMINEN, 'site_field' => 'instance'],
  ];

  public function __construct(
    private readonly EnvironmentResolverInterface $environmentResolver,
  ) {
  }

  /**
   * Whether the index is shared between instances.
   */
  public function isShared(string $indexId): bool {
    return isset(self::SHARED[$indexId]);
  }

  /**
   * The instance that may create, delete or rebuild this index.
   *
   * @return string|null
   *   The project name, or NULL when the index is not shared.
   */
  public function mainInstance(string $indexId): ?string {
    return self::SHARED[$indexId]['main'] ?? NULL;
  }

  /**
   * The index field that holds the name of the indexing instance.
   *
   * @return string|null
   *   The field id, or NULL when the index is not shared.
   */
  public function siteField(string $indexId): ?string {
    return self::SHARED[$indexId]['site_field'] ?? NULL;
  }

  /**
   * Whether the index is shared and this is not its main instance.
   */
  public function isSharedFromOtherInstance(string $indexId): bool {
    $main = $this->mainInstance($indexId);
    return $main !== NULL && $main !== $this->activeProject();
  }

  /**
   * The active project name, or NULL when it cannot be resolved.
   */
  private function activeProject(): ?string {
    try {
      return $this->environmentResolver->getActiveProject()->getName();
    }
    catch (EnvironmentResolverException) {
      return NULL;
    }
  }

}
