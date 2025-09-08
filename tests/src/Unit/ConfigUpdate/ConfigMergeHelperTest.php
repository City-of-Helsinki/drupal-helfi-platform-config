<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\ConfigUpdate;

use Drupal\helfi_platform_config\ConfigUpdate\ConfigMergeHelper;
use PHPUnit\Framework\TestCase;

/**
 * Tests the ConfigMergeHelper utility class.
 *
 * Tests the configuration array merging functionality with the
 * following test cases:
 * - Basic array merging with string keys and nested arrays
 * - Sequential array merging with deduplication
 * - Value overriding behavior in merged arrays.
 *
 * @coversDefaultClass \Drupal\helfi_platform_config\ConfigUpdate\ConfigMergeHelper
 * @group helfi_platform_config
 */
class ConfigMergeHelperTest extends TestCase {

  /**
   * Tests basic array merging with string keys and nested arrays.
   *
   * @covers ::mergeDeepArray
   */
  public function testMergeDeepArray(): void {
    $arrays = [
      [
        'key1' => 'value1',
        'key2' => ['nested1' => 'nestedvalue1'],
      ],
      [
        'key2' => ['nested2' => 'nestedvalue2'],
        'key3' => 'value3',
      ],
    ];

    $result = ConfigMergeHelper::mergeDeepArray($arrays);

    $expected = [
      'key1' => 'value1',
      'key2' => [
        'nested1' => 'nestedvalue1',
        'nested2' => 'nestedvalue2',
      ],
      'key3' => 'value3',
    ];

    $this->assertEquals($expected, $result);
  }

  /**
   * Tests sequential array merging with automatic deduplication.
   *
   * @covers ::mergeDeepArray
   */
  public function testMergeDeepArrayWithSequentialArrays(): void {
    $arrays = [
      [
        'items' => ['item1', 'item2'],
      ],
      [
      // Duplicate 'item1' should be removed.
        'items' => ['item3', 'item1'],
      ],
    ];

    $result = ConfigMergeHelper::mergeDeepArray($arrays);

    $expected = [
      'items' => ['item1', 'item2', 'item3'],
    ];

    $this->assertEquals($expected, $result);
    $this->assertCount(3, $result['items'], 'Duplicates should be removed');
  }

  /**
   * Tests that later values override earlier ones during merge.
   *
   * @covers ::mergeDeepArray
   */
  public function testMergeDeepArrayOverrideValues(): void {
    $arrays = [
      [
        'key1' => 'original_value',
        'key2' => 'keep_this',
      ],
      [
        'key1' => 'overridden_value',
      ],
    ];

    $result = ConfigMergeHelper::mergeDeepArray($arrays);

    $expected = [
      'key1' => 'overridden_value',
      'key2' => 'keep_this',
    ];

    $this->assertEquals($expected, $result);
    $this->assertEquals('overridden_value', $result['key1'], 'Later values should override earlier ones');
    $this->assertEquals('keep_this', $result['key2'], 'Non-conflicting values should be preserved');
  }

}
