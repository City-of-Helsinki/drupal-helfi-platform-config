<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Unit;

use Drupal\helfi_search\QueryRewriter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests query term canonicalization.
 */
#[Group('helfi_search')]
class QueryRewriterTest extends TestCase {

  /**
   * Tests that terms are canonicalized case-insensitively and whole-word only.
   *
   * @param string $expected
   *   The expected rewritten query.
   * @param string $query
   *   The input query.
   * @param array<int, string> $terms
   *   The canonical brand terms.
   */
  #[DataProvider('rewriteProvider')]
  public function testRewrite(string $expected, string $query, array $terms): void {
    $this->assertSame($expected, QueryRewriter::rewrite($query, $terms));
  }

  /**
   * Data provider for testRewrite().
   *
   * @return iterable<string, array{string, string, array<int, string>}>
   *   Test cases.
   */
  public static function rewriteProvider(): iterable {
    $terms = ['OmaStadi'];

    yield 'canonicalizes lowercase brand term' => ['OmaStadi', 'omastadi', $terms];
    yield 'matches regardless of casing' => ['OmaStadi', 'OMASTADI', $terms];
    yield 'rewrites term within a phrase' => ['what is OmaStadi about', 'what is omastadi about', $terms];
    yield 'already-canonical term is unchanged' => ['OmaStadi', 'OmaStadi', $terms];
    yield 'leaves a longer word untouched' => ['omastadium', 'omastadium', $terms];
    yield 'applies multiple terms' => [
      'OmaStadi and Stadin ammattiopisto',
      'omastadi and stadin ammattiopisto',
      ['OmaStadi', 'Stadin ammattiopisto'],
    ];
    yield 'empty query returns unchanged' => ['', '', $terms];
  }

}
