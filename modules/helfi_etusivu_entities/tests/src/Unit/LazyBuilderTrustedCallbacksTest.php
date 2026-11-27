<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_etusivu_entities\Unit;

use Drupal\helfi_etusivu_entities\AnnouncementsLazyBuilder;
use Drupal\helfi_etusivu_entities\SurveyLazyBuilder;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the lazy builder trusted callbacks.
 */
#[Group('helfi_etusivu_entities')]
final class LazyBuilderTrustedCallbacksTest extends UnitTestCase {

  /**
   * Tests that the builder exposes the lazy build callback.
   *
   * @param class-string<\Drupal\Core\Security\TrustedCallbackInterface> $class
   *   The builder class.
   */
  #[DataProvider('builderProvider')]
  public function testTrustedCallbacks(string $class): void {
    $this->assertSame(['lazyBuild'], $class::trustedCallbacks());
  }

  /**
   * Data provider for testTrustedCallbacks().
   *
   * @return array<string, array{class-string}>
   *   The builder classes.
   */
  public static function builderProvider(): array {
    return [
      'announcements' => [AnnouncementsLazyBuilder::class],
      'surveys' => [SurveyLazyBuilder::class],
    ];
  }

}
