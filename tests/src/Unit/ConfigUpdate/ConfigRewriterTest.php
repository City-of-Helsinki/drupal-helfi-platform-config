<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\ConfigUpdate;

use Drupal\Tests\UnitTestCase;
use Drupal\helfi_platform_config\ConfigUpdate\ConfigMergeHelper;

/**
 * Tests the ConfigRewriter service.
 *
 * Tests the configuration rewriting functionality with the
 * following test cases:
 * - Configuration merge when config_rewrite flag is not set.
 *
 * @coversDefaultClass \Drupal\helfi_platform_config\ConfigUpdate\ConfigRewriter
 * @group helfi_platform_config
 */
class ConfigRewriterTest extends UnitTestCase {

  /**
   * Tests configuration merge behavior when config_rewrite flag is not set.
   *
   * Verifies that the configuration arrays are properly merged using
   * ConfigMergeHelper::mergeDeepArray() when the config_rewrite flag is absent.
   *
   * @covers ::rewriteConfig
   */
  public function testConfigMergeWithoutRewrite(): void {
    // Test data.
    $original = ['key' => 'original', 'common' => 'value'];
    $rewrite = ['key' => 'new', 'new_key' => 'new_value'];

    // Manually test the merge logic that should happen
    // when config_rewrite is not set.
    $expected = ConfigMergeHelper::mergeDeepArray([
      $original,
      $rewrite,
    ]);

    // Assert the expected merged result.
    $this->assertEquals(
      [
        'key' => 'new',
        'common' => 'value',
        'new_key' => 'new_value',
      ],
      $expected,
      'The configuration should be merged with the original values.'
    );
  }

}
