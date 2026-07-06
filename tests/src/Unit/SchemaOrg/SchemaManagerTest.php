<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\SchemaOrg;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\helfi_platform_config\SchemaOrg\SchemaBuilderInterface;
use Drupal\helfi_platform_config\SchemaOrg\SchemaManager;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the schema.org graph manager.
 */
#[Group('helfi_platform_config')]
#[CoversClass(SchemaManager::class)]
class SchemaManagerTest extends UnitTestCase {

  /**
   * Tests that an empty document is returned when nothing is contributed.
   */
  public function testReturnsEmptyWhenNothingApplies(): void {
    $manager = $this->getSut();
    $manager->add($this->builder(FALSE, [['@type' => 'WebPage']]));

    $this->assertSame([], $manager->build(NULL, new CacheableMetadata()));
  }

  /**
   * Tests priority ordering and that empty nodes are filtered out.
   */
  public function testPriorityOrderingAndEmptyFiltering(): void {
    $manager = $this->getSut();
    $manager->add($this->builder(TRUE, [['@type' => 'Low']]), 10);
    $manager->add($this->builder(TRUE, [['@type' => 'High']]), 100);
    // Applies but contributes only empty nodes, which must be filtered out.
    $manager->add($this->builder(TRUE, [[], ['@type' => 'Mid']]), 50);

    $types = array_column($manager->build(NULL, new CacheableMetadata())['@graph'], '@type');

    $this->assertSame(['High', 'Mid', 'Low'], $types);
  }

  /**
   * Tests that builders can refine the shared cacheability object.
   */
  public function testForwardsCacheabilityToBuilders(): void {
    $manager = $this->getSut();
    $manager->add(new readonly class implements SchemaBuilderInterface {

      /**
       * {@inheritdoc}
       */
      public function applies(?EntityInterface $entity): bool {
        return TRUE;
      }

      /**
       * {@inheritdoc}
       */
      public function build(?EntityInterface $entity, RefinableCacheableDependencyInterface $cacheability): array {
        $cacheability->addCacheTags(['schema:custom']);
        return [['@type' => 'WebPage']];
      }

    });

    $cacheability = new CacheableMetadata();
    $manager->build(NULL, $cacheability);

    $this->assertContains('schema:custom', $cacheability->getCacheTags());
  }

  /**
   * Returns a SchemaManager.
   */
  private function getSut(): SchemaManager {
    $module_handler = $this->prophesize(ModuleHandlerInterface::class);
    return new SchemaManager($module_handler->reveal());
  }

  /**
   * Returns a builder that contributes the given nodes when it applies.
   *
   * @param bool $applies
   *   Whether the builder applies.
   * @param array<int, array<string, mixed>> $nodes
   *   Schema.org nodes the builder contributes.
   */
  private function builder(bool $applies, array $nodes): SchemaBuilderInterface {
    return new readonly class($applies, $nodes) implements SchemaBuilderInterface {

      /**
       * Constructs the stub builder.
       *
       * @param bool $applies
       *   Whether the builder applies.
       * @param array<int, array<string, mixed>> $nodes
       *   Schema.org nodes the builder contributes.
       */
      public function __construct(
        private bool $applies,
        private array $nodes,
      ) {
      }

      /**
       * {@inheritdoc}
       */
      public function applies(?EntityInterface $entity): bool {
        return $this->applies;
      }

      /**
       * {@inheritdoc}
       */
      public function build(?EntityInterface $entity, RefinableCacheableDependencyInterface $cacheability): array {
        return $this->nodes;
      }

    };
  }

}
