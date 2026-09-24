<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\Plugin\Linkit\Substitution;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Url;
use Drupal\helfi_platform_config\MultisiteContentId;
use Drupal\helfi_platform_config\Plugin\Linkit\Substitution\Multisite;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Multisite Linkit substitution plugin.
 */
#[CoversClass(Multisite::class)]
#[Group('helfi_platform_config')]
final class MultisiteTest extends UnitTestCase {

  /**
   * Tests that the plugin applies only to multisite content.
   */
  public function testIsApplicable(): void {
    $applicable = $this->createMock(EntityTypeInterface::class);
    $applicable->method('id')->willReturn(MultisiteContentId::ENTITY_TYPE_ID);
    $this->assertTrue(Multisite::isApplicable($applicable));

    $other = $this->createMock(EntityTypeInterface::class);
    $other->method('id')->willReturn('node');
    $this->assertFalse(Multisite::isApplicable($other));
  }

  /**
   * Tests that non-multisite entities fall back to the entity URL.
   */
  public function testGetUrlFallsBackToEntityUrl(): void {
    $url = $this->createMock(Url::class);
    $entity = $this->createMock(EntityInterface::class);
    $entity->expects($this->once())->method('toUrl')->willReturn($url);

    $this->assertSame($url, $this->createPlugin()->getUrl($entity));
  }

  /**
   * Creates the substitution plugin.
   */
  private function createPlugin(): Multisite {
    return new Multisite([], 'multisite', ['id' => 'multisite']);
  }

}
