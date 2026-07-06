<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Unit\Service;

use Drupal\helfi_ai\Service\AiSummaryGenerator;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the plain text to HTML bullet-list conversion.
 */
#[Group('helfi_ai')]
class AiSummaryFormattingTest extends UnitTestCase {

  /**
   * Invokes the private static AiSummaryGenerator::toHtmlBulletList() helper.
   *
   * @param string $plain
   *   The plain-text reply to convert.
   *
   * @return string
   *   The resulting HTML.
   */
  private static function toHtml(string $plain): string {
    $method = new \ReflectionMethod(AiSummaryGenerator::class, 'toHtmlBulletList');
    return $method->invoke(NULL, $plain);
  }

  /**
   * Each non-empty line becomes one list item.
   */
  public function testEachLineBecomesOneBullet(): void {
    $html = self::toHtml("First point\nSecond point\nThird point");
    $this->assertSame('<ul><li>First point</li><li>Second point</li><li>Third point</li></ul>', $html);
  }

  /**
   * Blank lines and surrounding whitespace are dropped.
   */
  public function testBlankLinesAndWhitespaceAreSkipped(): void {
    $html = self::toHtml("  One  \n\n\n  Two  ");
    $this->assertSame('<ul><li>One</li><li>Two</li></ul>', $html);
  }

  /**
   * Empty or whitespace-only input yields an empty string, not an empty list.
   */
  public function testEmptyInputYieldsEmptyString(): void {
    $this->assertSame('', self::toHtml(''));
    $this->assertSame('', self::toHtml("\n  \n"));
  }

  /**
   * HTML in the reply is escaped so it renders as text, never as markup.
   */
  public function testHtmlIsEscaped(): void {
    $html = self::toHtml('<script>alert("x")</script> & more');
    $this->assertSame(
      '<ul><li>&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; &amp; more</li></ul>',
      $html,
    );
  }

}
