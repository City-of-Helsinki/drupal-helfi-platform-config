<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Traits;

use Drupal\Core\DependencyInjection\ContainerBuilder;

/**
 * Overrides the embedding whitelist with the test's own entity types.
 */
trait AllowEmbeddingTrait {

  /**
   * The entity types allowed to produce embeddings during the test.
   *
   * @var array<array{entity_type: string, bundles?: array<string>}>
   */
  protected static array $whitelistedEntityTypes = [
    ['entity_type' => 'node', 'bundles' => ['test_node_bundle_1']],
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);

    $container->setParameter(
      'helfi_search.vectorized_entity_types',
      static::$whitelistedEntityTypes,
    );
  }

}
