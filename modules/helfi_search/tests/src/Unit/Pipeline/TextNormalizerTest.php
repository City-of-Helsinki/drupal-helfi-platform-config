<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Unit\Pipeline;

use Drupal\helfi_search\Pipeline\TextNormalizer;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the TextNormalizer pipeline service.
 */
#[Group('helfi_search')]
class TextNormalizerTest extends UnitTestCase {

  /**
   * Tests text normalization.
   */
  #[DataProvider('normalizeProvider')]
  public function testNormalize(string $input, string $expected): void {
    $this->assertSame($expected, (new TextNormalizer())->normalize($input));
  }

  /**
   * Data provider for testNormalize.
   */
  public static function normalizeProvider(): \Generator {
    // Empty and whitespace-only input.
    yield 'empty string' => ['', ''];
    yield 'whitespace only' => ["   \n\n\t  \n  ", ''];

    // Horizontal whitespace collapsing.
    yield 'multiple spaces' => ['hello    world', 'hello world'];
    yield 'tabs to space' => ["hello\t\tworld", 'hello world'];
    yield 'mixed horizontal whitespace' => ["a \t  b\t c", 'a b c'];

    // Newline handling.
    yield 'single newline preserved' => ["line one\nline two", "line one\nline two"];
    yield 'double newline preserved' => ["paragraph one\n\nparagraph two", "paragraph one\n\nparagraph two"];
    yield 'triple newline reduced to two' => ["a\n\n\nb", "a\n\nb"];
    yield 'five newlines reduced to two' => ["a\n\n\n\n\nb", "a\n\nb"];

    // Spaces around newlines.
    yield 'spaces around newline removed' => ["a \n b", "a\nb"];
    yield 'multiple spaces around newline removed' => ["a   \n   b", "a\nb"];

    // Trimming.
    yield 'leading and trailing spaces trimmed' => ['  hello  ', 'hello'];
    yield 'leading and trailing newlines trimmed' => ["\n\nhello\n\n", 'hello'];

    // Non-destructive: case, punctuation, special chars preserved.
    yield 'case preserved' => ['Helsinki IS a CITY', 'Helsinki IS a CITY'];
    yield 'punctuation preserved' => ['Hello, world! How you? Fine; thanks.', 'Hello, world! How you? Fine; thanks.'];
    yield 'special characters preserved' => ['Price: 50€ & 100$ — "quoted"', 'Price: 50€ & 100$ — "quoted"'];
    yield 'accented characters preserved' => ['Héllo wörld café résumé', 'Héllo wörld café résumé'];

    // Realistic input.
    yield 'realistic markdown' => [
      "## Section One  \n\n\n\n  Content here.   More text.  \n\n  ## Section Two  \n  Other content.",
      "## Section One\n\nContent here. More text.\n\n## Section Two\nOther content.",
    ];
  }

}
