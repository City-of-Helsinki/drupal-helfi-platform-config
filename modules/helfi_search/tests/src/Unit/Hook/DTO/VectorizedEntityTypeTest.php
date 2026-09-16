<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Unit\Hook\DTO;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\helfi_search\Hook\DTO\VectorizedEntityType;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the entity type matcher behind the embedding whitelist.
 */
#[Group('helfi_search')]
class VectorizedEntityTypeTest extends UnitTestCase {

  /**
   * Tests entity matching.
   *
   * @phpstan-param array{entity_type: string, bundles?: array<string>} $matcher
   */
  #[DataProvider('matchesProvider')]
  public function testMatches(array $matcher, string $entityType, string $bundle, bool $expected): void {
    $sut = VectorizedEntityType::fromArray($matcher);

    $this->assertSame($expected, $sut->matches($this->entity($entityType, $bundle)));
  }

  /**
   * Data provider for testMatches.
   */
  public static function matchesProvider(): \Generator {
    $node = ['entity_type' => 'node', 'bundles' => ['page', 'landing_page']];
    // An entry without bundles matches every bundle.
    $tpr = ['entity_type' => 'tpr_unit'];

    yield 'listed bundle' => [$node, 'node', 'page', TRUE];
    yield 'unlisted bundle' => [$node, 'node', 'news_item', FALSE];
    yield 'no bundles, any bundle' => [$tpr, 'tpr_unit', 'anything', TRUE];
  }

  /**
   * Creates a content entity stub.
   */
  private function entity(string $entityType, string $bundle): ContentEntityInterface {
    $entity = $this->prophesize(ContentEntityInterface::class);
    $entity->getEntityTypeId()->willReturn($entityType);
    $entity->bundle()->willReturn($bundle);

    return $entity->reveal();
  }

}
