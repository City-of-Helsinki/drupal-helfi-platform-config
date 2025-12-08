<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_media_chart\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\helfi_media_chart\UrlParserTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests UrlParserTrait.
 *
 * @group helfi_media_chart
 */
class UrlParserTraitTest extends UnitTestCase {

  use UrlParserTrait;

  /**
   * Tests chart provider URLs.
   *
   * @covers \Drupal\helfi_media_chart\UrlParserTrait::mediaUrlToUri
   * @covers \Drupal\helfi_media_chart\UrlParserTrait::assertMediaLink
   */
  #[DataProvider('getTestChartUrlData')]
  public function testChartUrl(string $expected) : void {
    $this->assertEquals($expected, (string) $this->mediaUrlToUri($expected));
  }

  /**
   * The data provider for testChartUrl().
   *
   * @return array
   *   The test data.
   */
  public static function getTestChartUrlData() : array {
    return [
      [
        'https://app.powerbi.com/view?r=eyJrIjoiYjE5OTFhMmEtMWYzNC00YjY2LTllODMtMzhhZDRiNTJiMDQ5IiwidCI6IjNmZWI2YmMxLWQ3MjItNDcyNi05NjZjLTViNThiNjRkZjc1MiIsImMiOjh9',
        'https://playground.powerbi.com/sampleReportEmbed',
      ],
    ];
  }

}
