<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\SchemaOrg;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Collects schema.org builders and assembles the page-level JSON-LD graph.
 */
final class SchemaManager {

  /**
   * Builders grouped by priority.
   *
   * @phpstan-var array<int, \Drupal\helfi_platform_config\SchemaOrg\SchemaBuilderInterface[]>
   */
  private array $builders = [];

  /**
   * Sorted builders, highest priority first.
   *
   * @phpstan-var \Drupal\helfi_platform_config\SchemaOrg\SchemaBuilderInterface[]
   */
  private array $sortedBuilders = [];

  /**
   * Constructs a new instance.
   */
  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {
  }

  /**
   * Adds a schema builder.
   */
  public function add(SchemaBuilderInterface $builder, int $priority = 0): void {
    $this->builders[$priority][] = $builder;
    $this->sortedBuilders = [];
  }

  /**
   * Builds the full JSON-LD document for the given page entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   The main entity of the current page, or NULL when the page has none.
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface $cacheability
   *   Cacheability that applicable builders refine with the cache dependencies
   *   their output relies on.
   *
   * @return array<string, mixed>
   *   The JSON-LD document with "@context" and "@graph", or an empty array if
   *   no builder contributed anything.
   */
  public function build(?EntityInterface $entity, RefinableCacheableDependencyInterface $cacheability): array {
    $graph = [];

    foreach ($this->getBuilders() as $builder) {
      if (!$builder->applies($entity)) {
        continue;
      }
      foreach ($builder->build($entity, $cacheability) as $node) {
        if (!empty($node)) {
          $graph[] = $node;
        }
      }
    }

    // Let sites post-process or extend the graph.
    $this->moduleHandler->alter('schema_org', $graph, $entity);

    // Drop null / empty leaves so no empty schema.org properties are emitted.
    // This allows simpler builders, since they can just emit Drupal fields
    // as-is and the builder ensures that empty schema fields are dropped.
    $graph = array_values(array_filter(array_map(
      static fn (array $node): array => self::clean($node),
      $graph
    )));

    if (!$graph) {
      return [];
    }

    return [
      '@context' => 'https://schema.org',
      '@graph' => $graph,
    ];
  }

  /**
   * Recursively removes null, empty-string and empty-array values.
   *
   * @param array<mixed> $data
   *   The schema.org entity (or sub-structure) to clean.
   *
   * @return array<mixed>
   *   The cleaned structure.
   */
  private static function clean(array $data): array {
    foreach ($data as $key => $value) {
      if (is_array($value)) {
        $value = self::clean($value);
        $data[$key] = $value;
      }

      if ($value === NULL || $value === '' || $value === []) {
        unset($data[$key]);
      }
    }

    return $data;
  }

  /**
   * Gets the builders sorted by priority, highest first.
   *
   * @return \Drupal\helfi_platform_config\SchemaOrg\SchemaBuilderInterface[]
   *   Sorted builders.
   */
  private function getBuilders(): array {
    if (empty($this->sortedBuilders)) {
      krsort($this->builders);
      $this->sortedBuilders = $this->builders ? array_merge(...$this->builders) : [];
    }

    return $this->sortedBuilders;
  }

}
