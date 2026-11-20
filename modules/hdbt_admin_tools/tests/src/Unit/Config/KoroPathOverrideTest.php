<?php

declare(strict_types=1);

namespace Drupal\Tests\hdbt_admin_tools\Unit\Config;

use Drupal\Core\Config\StorageInterface;
use Drupal\hdbt_admin_tools\Config\KoroPathOverride;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the koro path override.
 *
 * @group hdbt_admin_tools
 */
final class KoroPathOverrideTest extends UnitTestCase {

  /**
   * The stored configuration.
   *
   * @var array<string, mixed>
   */
  private const array CONFIG = [
    KoroPathOverride::CONFIG_NAME => [
      'koro_overrides' => [
        ['path' => '/a', 'koro' => 'wave'],
        ['path' => '/a/b', 'koro' => 'calm'],
        ['path' => '/Mixed-Case', 'koro' => 'beat'],
        ['path' => '/trailing/', 'koro' => 'vibration'],
        ['path' => '', 'koro' => 'pulse'],
        ['path' => '/', 'koro' => 'wave'],
      ],
    ],
    'language.negotiation' => [
      'url' => [
        'prefixes' => ['fi' => 'fi', 'sv' => 'sv', 'en' => 'en'],
      ],
    ],
    'helfi_proxy.settings' => [
      'prefixes' => [
        'fi' => 'kulttuuri-ja-vapaa-aika',
        'sv' => 'kultur-och-fritid',
        'en' => 'culture-and-leisure',
      ],
    ],
  ];

  /**
   * Create the override for a request path.
   *
   * @param string|null $path
   *   The request path or null for no request.
   *
   * @return \Drupal\hdbt_admin_tools\Config\KoroPathOverride
   *   The override.
   */
  private function createOverride(?string $path): KoroPathOverride {
    $storage = $this->createMock(StorageInterface::class);
    $storage->method('read')
      ->willReturnCallback(fn (string $name) => self::CONFIG[$name] ?? FALSE);

    $requestStack = new RequestStack();

    if ($path !== NULL) {
      $requestStack->push(Request::create($path));
    }
    return new KoroPathOverride($storage, $requestStack);
  }

  /**
   * Tests the koro for request paths.
   */
  #[DataProvider('providerGetKoro')]
  public function testGetKoro(string $path, ?string $expected): void {
    $this->assertSame($expected, $this->createOverride($path)->getKoro());
  }

  /**
   * Data provider for testGetKoro().
   *
   * @return array<string, array{string, string|null}>
   *   The request path and the expected koro.
   */
  public static function providerGetKoro(): array {
    return [
      'Rule applies to its own path' => ['/fi/kulttuuri-ja-vapaa-aika/a', 'wave'],
      'Rule applies to its subpaths' => ['/fi/kulttuuri-ja-vapaa-aika/a/c', 'wave'],
      'Most specific rule wins' => ['/fi/kulttuuri-ja-vapaa-aika/a/b', 'calm'],
      'Rule does not match a partial segment' => ['/fi/kulttuuri-ja-vapaa-aika/ab', NULL],
      'Rule matches regardless of case' => ['/fi/kulttuuri-ja-vapaa-aika/mixed-case', 'beat'],
      'Request path matches regardless of case' => ['/FI/Kulttuuri-ja-vapaa-aika/A', 'wave'],
      'Rule with a trailing slash matches' => ['/fi/kulttuuri-ja-vapaa-aika/trailing', 'vibration'],
      'Request path with a trailing slash matches' => ['/fi/kulttuuri-ja-vapaa-aika/a/', 'wave'],
      'Other language and site prefixes are removed' => ['/en/culture-and-leisure/a', 'wave'],
      'Path without prefixes matches' => ['/a', 'wave'],
      'Path with only the language prefix matches' => ['/sv/a', 'wave'],
      'Rule without a path is skipped on the front page' => ['/fi/kulttuuri-ja-vapaa-aika', NULL],
      'Unmatched path has no koro' => ['/fi/kulttuuri-ja-vapaa-aika/other', NULL],
    ];
  }

  /**
   * Tests that no koro is resolved without a request.
   */
  public function testGetKoroWithoutRequest(): void {
    $this->assertNull($this->createOverride(NULL)->getKoro());
  }

  /**
   * Tests the overrides for the appearance settings.
   */
  public function testLoadOverrides(): void {
    $this->assertSame(
      [KoroPathOverride::CONFIG_NAME => ['site_settings' => ['koro' => 'wave']]],
      $this->createOverride('/fi/a')->loadOverrides([KoroPathOverride::CONFIG_NAME]),
    );
  }

  /**
   * Tests that unmatched paths are not overridden.
   */
  public function testLoadOverridesForUnmatchedPath(): void {
    $this->assertSame([], $this->createOverride('/fi/other')->loadOverrides([KoroPathOverride::CONFIG_NAME]));
  }

  /**
   * Tests the cacheable metadata.
   */
  public function testGetCacheableMetadata(): void {
    $metadata = $this->createOverride('/fi/a')->getCacheableMetadata(KoroPathOverride::CONFIG_NAME);
    $this->assertSame(['url.path'], $metadata->getCacheContexts());
    $this->assertSame(['config:' . KoroPathOverride::CONFIG_NAME], $metadata->getCacheTags());
  }

}
