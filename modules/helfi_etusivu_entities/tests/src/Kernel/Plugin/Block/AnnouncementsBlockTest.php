<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_etusivu_entities\Unit;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\helfi_etusivu_entities\AnnouncementsLazyBuilder;
use Drupal\helfi_etusivu_entities\Plugin\Block\AnnouncementsBlock;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

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

  /**
   * Test announcements lazy building.
   */
  public function testAnnouncementLazyBuild(): void {
    $announcement = Node::create([
      'uuid' => 'c9ee55c3-9ca5-4c53-900e-82b6d6928a63',
      'type' => 'announcement',
      'langcode' => 'en',
      'body' => 'body',
      'title' => 'title',
      'status' => NodeInterface::PUBLISHED,
      'field_announcement_title' => 'The title',
      'field_announcement_type' => 'notification',
      'field_announcement_all_pages' => 1,
    ]);
    $announcement->save();

    $announcement = Node::create([
      'uuid' => 'c9ee55c3-9ca5-4c53-900e-82b6d6928a64',
      'type' => 'announcement',
      'langcode' => 'en',
      'body' => 'body',
      'title' => 'title2',
      'status' => NodeInterface::PUBLISHED,
      'field_announcement_title' => 'The title2',
      'field_announcement_type' => 'alert',
      'field_announcement_all_pages' => 0,
    ]);
    $announcement->save();

    $node = Node::create(['type' => 'page', 'langcode' => 'en', 'title' => 'titlele']);
    $node->save();

    $routeMatch = $this->prophesize(RouteMatchInterface::class);
    $routeMatch->getParameter('node')->willReturn($node);
    $routeMatch = $routeMatch->reveal();

    $this->container->set('current_route_match', $routeMatch);

    $announcementLazyBuilder = $this->container->get(AnnouncementsLazyBuilder::class);
    $result = $announcementLazyBuilder->lazyBuild(TRUE);
    $this->assertTrue($result['#sorted']);
  }

}
