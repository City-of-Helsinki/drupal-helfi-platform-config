<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit;

use Drupal\helfi_platform_config\HeadingSlugger;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the HeadingSlugger component.
 */
#[Group('helfi_platform_config')]
class HeadingSluggerTest extends UnitTestCase {

  /**
   * Tests slug generation.
   */
  #[DataProvider('slugData')]
  public function testSlug(string $langcode, string $input, string $expected): void {
    $sut = new HeadingSlugger($langcode);

    $this->assertSame($expected, $sut->slug($input));
  }

  /**
   * Data provider for `testSlug`.
   *
   * @phpstan-return array<string, array{string, string, string}>
   */
  public static function slugData(): array {
    return [
      // Basic ASCII headings get hyphenated and lowercased.
      'ascii heading' => ['en', 'How to Apply', 'how-to-apply'],

      // Main languages (fi/sv/en) replace 'ääkköset'.
      'fi: paatos' => ['fi', 'Päätös', 'paatos'],
      'sv: hojdpunkter' => ['sv', 'Höjdpunkter', 'hojdpunkter'],
      'en: arstid' => ['en', 'Årstid', 'arstid'],

      // Non-main languages get the full transliteration table.
      'ru: cyrillic' => ['ru', 'Привет', 'privet'],
      'ru: greek' => ['ru', 'Καλημέρα', 'kalhmera'],

      // Trailing digits switch to underscore.
      'trailing single digit' => ['en', 'Section 1', 'section_1'],
      'trailing multi digit' => ['en', 'Chapter 42', 'chapter_42'],

      // Trailing digit rule does not fire for digits embedded mid-string.
      'mid-string digits stay hyphenated' => ['en', 'Top 10 Things', 'top-10-things'],

      // Empty / whitespace-only input produces an empty slug.
      'empty string' => ['en', '', ''],
      'whitespace only' => ['en', '   ', ''],

      // Punctuation collapses to hyphens, matching the JS \W rule.
      'punctuation collapses to hyphens' => ['en', "What's new?", 'what-s-new-'],
    ];
  }

  /**
   * Tests duplicate headings get JS-compatible suffixes: name, name-2, name-3.
   *
   * "-1" is skipped when colliding with a previously injected anchor.
   */
  public function testDuplicateHeadingDeduplication(): void {
    $sut = new HeadingSlugger('en');

    $this->assertSame('intro', $sut->slug('Intro'));
    $this->assertSame('intro-2', $sut->slug('Intro'));
    $this->assertSame('intro-3', $sut->slug('Intro'));
  }

  /**
   * Tests collision against a reserved page ID.
   */
  public function testCollisionWithReservedIdUsesMinusOne(): void {
    $sut = new HeadingSlugger('en', ['intro']);

    $this->assertSame('intro-1', $sut->slug('Intro'));
    $this->assertSame('intro-2', $sut->slug('Intro'));
  }

}
