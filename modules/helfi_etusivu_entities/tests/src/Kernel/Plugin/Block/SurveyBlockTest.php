<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_etusivu_entities\Kernel\Plugin\Block;

use Drupal\helfi_etusivu_entities\Plugin\Block\SurveyBlock;
use Drupal\helfi_etusivu_entities\SurveyLazyBuilder;

/**
 * Tests Survey blocks.
 *
 * @group helfi_platform_config
 */
class SurveyBlockTest extends BlockTestBase {

  /**
   * Make sure build() works.
   */
  public function testBuild(): void {
    $block = SurveyBlock::create($this->container, [
      'use_remote_entities' => FALSE,
    ], 'announcement', ['provider' => 'helfi_announcement']);
    $result = $block->build();
    $this->assertTrue(isset($result['#lazy_builder']));
  }

  /**
   * Test survey lazy building.
   */
  public function testSurveyLazyBuild(): void {
    $announcementLazyBuilder = $this->container->get(SurveyLazyBuilder::class);
    $result = $announcementLazyBuilder->lazyBuild(TRUE);
    $this->assertTrue($result['#sorted']);
  }

}
