<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_etusivu_entities\Unit;

use Drupal\Component\Utility\Xss;
use Drupal\helfi_etusivu_entities\Plugin\Block\AnnouncementsBlock;
use Drupal\helfi_etusivu_entities\SurveyLazyBuilder;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;

/**
 * Tests Survey blocks.
 *
 * @group helfi_platform_config
 */
class SurveyBlockTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_api_base',
    'helfi_platform_config',
    'node',
    'link',
    'language',
    'allowed_formats',
    'select2',
    'content_translation',
    'text',
    'options',
    'menu_ui',
    'scheduler',
    'config_rewrite',
    'helfi_node_survey',
    'external_entities',
    'helfi_etusivu_entities',
    'publication_date',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');

    $this->installConfig([
      'node',
      'helfi_node_survey',
      'helfi_etusivu_entities',
    ]);
  }

  /**
   * Make sure build() works.
   *
   * @covers Drupal\helfi_etusivu_entities\Plugin\Block\SurveyBlock
   */
  public function testBuild(): void {
    $block = AnnouncementsBlock::create($this->container, [
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
