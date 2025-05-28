<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_etusivu_entities\Unit;

use Drupal\external_entities\ExternalEntityInterface;
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
    // Create testing data.
    $this->createAnnouncements();

    $announcementLazyBuilder = $this->container->get(AnnouncementsLazyBuilder::class);
    $result = $announcementLazyBuilder->lazyBuild(TRUE);
    $this->assertTrue($result['#sorted']);
  }

  /**
   * Test loading remote announcements.
   */
  public function testRemoteLazyLoad(): void {
    // Create testing data.
    $externalEntity = $this->createExternalEntity();

    $announcementLazyBuilder = $this->container->get(AnnouncementsLazyBuilder::class);
    $result = $announcementLazyBuilder->handleRemoteEntities([$externalEntity]);
    $this->assertNotEmpty($result);
  }

  /**
   * Create announcement entities for tests.
   */
  private function createAnnouncements(): void {
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
      'field_announcement_all_pages' => 1,
    ]);
    $announcement->save();

    $announcement = Node::create([
      'uuid' => 'c9ee55c3-9ca5-4c53-900e-82b6d6928a65',
      'type' => 'announcement',
      'langcode' => 'en',
      'body' => 'body',
      'title' => 'title3',
      'status' => NodeInterface::PUBLISHED,
      'field_announcement_title' => 'The title3',
      'field_announcement_type' => 'alert',
      'field_announcement_all_pages' => 0,
    ]);
    $announcement->save();
  }

  /**
   * Create external entity for tests.
   *
   * @return \Drupal\external_entities\ExternalEntityInterface
   *   An external entity.
   */
  private function createExternalEntity(): ExternalEntityInterface {
    $storage = $this->container
      ->get('entity_type.manager')
      ->getStorage('helfi_announcements');

    return $storage->create([
      'uuid' => 'c9ee55c3-9ca5-4c53-900e-82b6d6928a99',
      'langcode' => 'en',
      'body' => 'body',
      'title' => 'title3',
      'status' => NodeInterface::PUBLISHED,
      'field_announcement_title' => 'The title3',
      'field_announcement_type' => 'alert',
      'field_announcement_all_pages' => 0,
      'field_announcement_assistive_technology_close_button_title' => 'assistance',
    ]);
  }

}
