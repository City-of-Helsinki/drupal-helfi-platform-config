<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit;

use Drupal\helfi_platform_config\MultisiteContentId;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests MultisiteContentId path conversion.
 */
#[CoversClass(MultisiteContentId::class)]
#[Group('helfi_platform_config')]
class MultisiteContentIdTest extends UnitTestCase {

  /**
   * Tests round-trip conversion between source ids and path segments.
   *
   * @phpstan-param array{instance: string, datasource: string, item: string} $expected_segments
   */
  #[DataProvider('providerSourceIds')]
  public function testPathSegmentRoundTrip(string $source_id, array $expected_segments): void {
    $this->assertSame($expected_segments, MultisiteContentId::toPathSegments($source_id));
    $this->assertSame($source_id, MultisiteContentId::fromPathSegments(
      $expected_segments['instance'],
      $expected_segments['datasource'],
      $expected_segments['item'],
    ));
  }

  /**
   * Tests extracting a source id from a canonical path.
   */
  #[DataProvider('providerUserInput')]
  public function testExtractFromUserInput(string $value, ?string $expected): void {
    $this->assertSame($expected, MultisiteContentId::extractFromUserInput($value));
  }

  /**
   * Source ids and their canonical path segments.
   *
   * @return array<string, array{0: string, 1: array{instance: string, datasource: string, item: string}}>
   *   Test cases.
   */
  public static function providerSourceIds(): array {
    return [
      'etusivu english node' => [
        'site_etusivu/entity:node/8420:en',
        [
          'instance' => 'site_etusivu',
          'datasource' => 'entity-node',
          'item' => '8420-en',
        ],
      ],
      'instance name containing dashes' => [
        'site_kasvatus-koulutus/entity:node/12:fi',
        [
          'instance' => 'site_kasvatus-koulutus',
          'datasource' => 'entity-node',
          'item' => '12-fi',
        ],
      ],
    ];
  }

  /**
   * User input values and extracted source ids.
   *
   * @return array<string, array{0: string, 1: string|null}>
   *   Test cases.
   */
  public static function providerUserInput(): array {
    return [
      'language prefixed path' => [
        '/fi/helfi-multisite-content/site_etusivu/entity-node/8420-en',
        'site_etusivu/entity:node/8420:en',
      ],
      'path without language prefix' => [
        '/helfi-multisite-content/site_kasvatus-koulutus/entity-node/12-fi',
        'site_kasvatus-koulutus/entity:node/12:fi',
      ],
      'encoded colons still decode' => [
        '/en/helfi-multisite-content/site_etusivu/entity-node/8420-en',
        'site_etusivu/entity:node/8420:en',
      ],
      'absolute url' => [
        'https://www.hel.fi/fi/helfi-multisite-content/site_etusivu/entity-node/8420-en',
        'site_etusivu/entity:node/8420:en',
      ],
      'unrelated search string' => [
        'library opening hours',
        NULL,
      ],
      'old underscore path is ignored' => [
        '/fi/helfi_multisite_content/site_etusivu/entity-node/8420-en',
        NULL,
      ],
    ];
  }

}
