<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_etusivu_entities\Unit;

use Drupal\helfi_etusivu_entities\AnnouncementsLazyBuilder;
use Drupal\helfi_etusivu_entities\Plugin\Block\AnnouncementsBlock;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Tests announcements block.
 *
 * @group helfi_platform_config
 */
class AnnouncementsBlockTest extends EntityKernelTestBase {

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
    'helfi_node_announcement',
    'external_entities',
    'helfi_etusivu_entities',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installConfig([
      'node',
      'helfi_node_announcement',
      'helfi_etusivu_entities',
    ]);
  }

  /**
   * Make sure build() works.
   *
   * @todo Improve these.
   */
  public function testBuild(): void {
    $block = AnnouncementsBlock::create($this->container, [
      'use_remote_entities' => FALSE,
    ], 'announcement', ['provider' => 'helfi_announcement']);
    $result = $block->build();
    $this->assertTrue(isset($result['#lazy_builder']));
  }

  public function testAnnouncementLazyBuild(): void {
    $announcementLazyBuilder = $this->container->get(AnnouncementsLazyBuilder::class);
    $result = $announcementLazyBuilder->lazyBuild();
    $this->assertTrue($result['#sorted']);
  }

}
