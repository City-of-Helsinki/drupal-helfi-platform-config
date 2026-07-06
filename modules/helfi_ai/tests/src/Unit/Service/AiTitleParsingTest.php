<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Unit\Service;

use Drupal\helfi_ai\Service\AiTitleSuggester;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the chat-reply to title candidates parsing.
 */
#[Group('helfi_ai')]
class AiTitleParsingTest extends UnitTestCase {

  /**
   * Invokes the private static AiTitleSuggester::parseTitles() helper.
   *
   * @param string $plain
   *   The raw chat reply to parse.
   *
   * @return string[]
   *   The parsed title candidates.
   */
  private static function parse(string $plain): array {
    $method = new \ReflectionMethod(AiTitleSuggester::class, 'parseTitles');
    return $method->invoke(NULL, $plain);
  }

  /**
   * Each non-empty line becomes one candidate.
   */
  public function testEachLineBecomesOneCandidate(): void {
    $this->assertSame(
      ['First title', 'Second title', 'Third title'],
      self::parse("First title\nSecond title\nThird title"),
    );
  }

  /**
   * Blank lines and surrounding whitespace are dropped.
   */
  public function testBlankLinesAndWhitespaceAreSkipped(): void {
    $this->assertSame(
      ['One', 'Two'],
      self::parse("  One  \n\n\n  Two  "),
    );
  }

  /**
   * Leading list markers are stripped.
   */
  public function testListMarkersAreStripped(): void {
    $this->assertSame(
      ['Alpha', 'Beta', 'Gamma'],
      self::parse("1. Alpha\n2) Beta\n- Gamma"),
    );
  }

  /**
   * Surrounding single and double quotes are stripped.
   */
  public function testSurroundingQuotesAreStripped(): void {
    $this->assertSame(
      ['Quoted title', 'Single quoted'],
      self::parse("\"Quoted title\"\n'Single quoted'"),
    );
  }

  /**
   * No more than three candidates are returned, even if the model returns more.
   */
  public function testResultIsCappedAtThree(): void {
    $titles = self::parse("One\nTwo\nThree\nFour\nFive");
    $this->assertCount(3, $titles);
    $this->assertSame(['One', 'Two', 'Three'], $titles);
  }

  /**
   * Empty or whitespace-only input yields an empty array.
   */
  public function testEmptyInputYieldsEmptyArray(): void {
    $this->assertSame([], self::parse(''));
    $this->assertSame([], self::parse("\n  \n"));
  }

}
