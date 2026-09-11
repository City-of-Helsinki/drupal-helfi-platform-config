<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Hook\DTO;

use Drupal\Core\Entity\EntityInterface;

/**
 * Built from the 'helfi_search.vectorized_entity_types' service parameter.
 *
 * @phpstan-type Matcher array{entity_type: string, bundles?: array<string>}
 */
final readonly class VectorizedEntityType {

  /**
   * Constructs a new instance.
   *
   * @param string $entityType
   *   The entity type id.
   * @param array<string> $bundles
   *   The bundles.
   */
  public function __construct(
    public string $entityType,
    public array $bundles,
  ) {
  }

  /**
   * Creates an instance from a service parameter matcher.
   *
   * @param Matcher $matcher
   *   The matcher.
   *
   * @return self
   *   The entity type matcher.
   */
  public static function fromArray(array $matcher): self {
    return new self(
      entityType: $matcher['entity_type'],
      bundles: $matcher['bundles'] ?? [],
    );
  }

  /**
   * Checks whether an entity should be vectorized.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   */
  public function matches(EntityInterface $entity): bool {
    if ($this->entityType !== $entity->getEntityTypeId()) {
      return FALSE;
    }
    // An empty bundle list matches any bundle.
    return !$this->bundles || in_array($entity->bundle(), $this->bundles, TRUE);
  }

}
