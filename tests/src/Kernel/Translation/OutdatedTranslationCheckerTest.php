<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\Translation;

use Drupal\helfi_platform_config\Translation\OutdatedTranslationChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the outdated translation checker.
 */
#[CoversClass(OutdatedTranslationChecker::class)]
#[Group('helfi_platform_config')]
#[RunTestsInSeparateProcesses]
class OutdatedTranslationCheckerTest extends OutdatedTranslationTestBase {

  /**
   * Gets the checker.
   *
   * @return \Drupal\helfi_platform_config\Translation\OutdatedTranslationChecker
   *   The checker.
   */
  private function checker(): OutdatedTranslationChecker {
    return $this->container->get(OutdatedTranslationChecker::class);
  }

  /**
   * Tests a translation older than the original.
   */
  public function testOutdatedTranslation(): void {
    $node = $this->createTranslatedNode(self::ORIGINAL, [
      'sv' => self::ORIGINAL - 21 * self::DAY,
      'en' => self::ORIGINAL - self::DAY,
    ]);

    $this->assertSame(21, $this->checker()->getOutdatedDays($node->getTranslation('sv')));
    $this->assertNull($this->checker()->getOutdatedDays($node->getTranslation('en')));
    $this->assertSame(['sv' => 21], $this->checker()->getOutdatedTranslations($node));
  }

  /**
   * Tests that a translation must be more than two weeks older.
   */
  public function testThreshold(): void {
    $node = $this->createTranslatedNode(self::ORIGINAL, [
      'sv' => self::ORIGINAL - OutdatedTranslationChecker::THRESHOLD,
      'en' => self::ORIGINAL - OutdatedTranslationChecker::THRESHOLD - 1,
    ]);

    $this->assertSame(['en' => 14], $this->checker()->getOutdatedTranslations($node));
  }

  /**
   * Tests that only translations older than the original are outdated.
   */
  public function testOriginalOlderThanTranslation(): void {
    $node = $this->createTranslatedNode(self::ORIGINAL, [
      'sv' => self::ORIGINAL + 30 * self::DAY,
    ]);

    $this->assertNull($this->checker()->getOutdatedDays($node->getTranslation('sv')));
    $this->assertSame([], $this->checker()->getOutdatedTranslations($node));
  }

  /**
   * Tests translations without a 'changed_at' value.
   */
  public function testMissingValue(): void {
    $node = $this->createTranslatedNode(self::ORIGINAL, ['sv' => NULL]);
    $this->assertNull($this->checker()->getChangedTime($node->getTranslation('sv')));
    $this->assertSame([], $this->checker()->getOutdatedTranslations($node));

    $node = $this->createTranslatedNode(NULL, ['sv' => self::ORIGINAL - 30 * self::DAY]);
    $this->assertSame([], $this->checker()->getOutdatedTranslations($node));
  }

  /**
   * Tests a bundle without the 'changed_at' field.
   */
  public function testBundleWithoutField(): void {
    $node = $this->createTranslatedNode(NULL, ['sv' => NULL], 'article');

    $this->assertNull($this->checker()->getChangedTime($node));
    $this->assertSame([], $this->checker()->getOutdatedTranslations($node));
  }

  /**
   * Tests that a new, unsaved translation is not outdated.
   */
  public function testNewTranslation(): void {
    $node = $this->createTranslatedNode(self::ORIGINAL, []);
    $translation = $node->addTranslation('sv', [
      'title' => 'sv',
      'changed_at' => self::ORIGINAL - 30 * self::DAY,
    ]);

    $this->assertNull($this->checker()->getOutdatedDays($translation));
  }

}
