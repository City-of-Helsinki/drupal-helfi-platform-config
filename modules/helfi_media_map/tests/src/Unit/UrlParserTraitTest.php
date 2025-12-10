<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_media_map\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\helfi_media_map\UrlParserTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests UrlParserTrait.
 *
 * @group helfi_media_map
 */
class UrlParserTraitTest extends UnitTestCase {

  use UrlParserTrait;

  /**
   * Tests that we can convert links to canonical map links.
   *
   * @covers \Drupal\helfi_media_map\UrlParserTrait::assertMediaLink
   * @covers \Drupal\helfi_media_map\UrlParserTrait::getMapUrl
   */
  #[DataProvider('getTestMapUrlData')]
  public function testMapUrl(string $url, string $expected) : void {
    $this->assertEquals($expected, $this->getMapUrl($url));
  }

  /**
   * The data provider for testGetLinkToMap().
   *
   * @return array
   *   The test data.
   */
  public static function getTestMapUrlData() : array {
    return [
      [
        'https://palvelukartta.hel.fi/fi/embed/?bbox=60.110894650782555,24.841289520263675,60.21824652560657,25.19937515258789&city=helsinki,espoo,vantaa,kauniainen',
        'https://palvelukartta.hel.fi/fi/?bbox=60.110894650782555,24.841289520263675,60.21824652560657,25.19937515258789&city=helsinki,espoo,vantaa,kauniainen',
      ],
      [
        'https://palvelukartta.hel.fi/fi?bbox=60.110894650782555,24.841289520263675,60.21824652560657,25.19937515258789&city=helsinki,espoo,vantaa,kauniainen',
        'https://palvelukartta.hel.fi/fi?bbox=60.110894650782555,24.841289520263675,60.21824652560657,25.19937515258789&city=helsinki,espoo,vantaa,kauniainen',
      ],
      [
        'https://kartta.hel.fi/embed?link=9UFyxc',
        'https://kartta.hel.fi/?link=9UFyxc',
      ],
      [
        'https://kartta.hel.fi/link/9uj8cj',
        'https://kartta.hel.fi/link/9uj8cj',
      ],
      [
        'https://palvelukartta.hel.fi/fi/embed/unit/56241?p=1&t=accessibilityDetails',
        'https://palvelukartta.hel.fi/fi/unit/56241?p=1&t=accessibilityDetails',
      ],
    ];
  }

  /**
   * Tests that we can convert links to embed urls.
   *
   * @covers \Drupal\helfi_media_map\UrlParserTrait::getEmbedUrl
   * @covers \Drupal\helfi_media_map\UrlParserTrait::assertMediaLink
   */
  #[DataProvider('getTestEmbedLink')]
  public function testEmbedLink(string $url, string $expected) : void {
    $this->assertEquals($expected, $this->getEmbedUrl($url));
  }

  /**
   * The data provider for testGetLinkToMap().
   *
   * @return array
   *   The test data.
   */
  public static function getTestEmbedLink() : array {
    return [
      [
        'https://palvelukartta.hel.fi/fi?bbox=60.110894650782555,24.841289520263675,60.21824652560657,25.19937515258789&city=helsinki,espoo,vantaa,kauniainen',
        'https://palvelukartta.hel.fi/fi/embed?bbox=60.110894650782555,24.841289520263675,60.21824652560657,25.19937515258789&city=helsinki,espoo,vantaa,kauniainen',
      ],
      [
        'https://palvelukartta.hel.fi/?bbox=60.110894650782555,24.841289520263675,60.21824652560657,25.19937515258789&city=helsinki,espoo,vantaa,kauniainen',
        'https://palvelukartta.hel.fi/fi/embed?bbox=60.110894650782555,24.841289520263675,60.21824652560657,25.19937515258789&city=helsinki,espoo,vantaa,kauniainen',
      ],
      [
        'https://palvelukartta.hel.fi/embed/?bbox=60.110,24.84,60.21,25.19&city=helsinki',
        'https://palvelukartta.hel.fi/embed/?bbox=60.110,24.84,60.21,25.19&city=helsinki',
      ],
      [
        'https://palvelukartta.hel.fi/fi/embed/?bbox=60.110,24.84,60.21,25.19&city=helsinki',
        'https://palvelukartta.hel.fi/fi/embed/?bbox=60.110,24.84,60.21,25.19&city=helsinki',
      ],
      [
        'https://palvelukartta.hel.fi/fi/unit/56241?p=1&t=accessibilityDetails',
        'https://palvelukartta.hel.fi/fi/embed/unit/56241?p=1&t=accessibilityDetails',
      ],
      [
        'https://palvelukartta.hel.fi/fi/embed/unit/56241?p=1&t=accessibilityDetails',
        'https://palvelukartta.hel.fi/fi/embed/unit/56241?p=1&t=accessibilityDetails',
      ],
      [
        'https://kartta.hel.fi/embed?link=123',
        'https://kartta.hel.fi/embed?link=123',
      ],
      [
        'https://kartta.hel.fi/?link=345',
        'https://kartta.hel.fi/embed?link=345',
      ],
      [
        'https://kartta.hel.fi/link/678',
        'https://kartta.hel.fi/embed?link=678',
      ],
    ];
  }

}
