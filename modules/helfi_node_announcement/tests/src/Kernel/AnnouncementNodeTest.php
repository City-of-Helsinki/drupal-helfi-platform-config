<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_node_announcement\Kernel;

use Drupal\helfi_node_announcement\Entity\Announcement;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the Announcement node entity class.
 *
 * @group helfi_node_announcement
 */
class AnnouncementNodeTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'allowed_formats',
    'content_translation',
    'field',
    'helfi_node_announcement',
    'language',
    'link',
    'menu_link_content',
    'menu_ui',
    'node',
    'options',
    'scheduler',
    'system',
    'select2',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installConfig(['system', 'node', 'helfi_node_announcement']);
    $this->installSchema('node', ['node_access']);
  }

  /**
   * Tests the getAnnouncementType and getLabels methods.
   */
  public function testAnnouncementMethods(): void {
    $values = [
      'type' => 'announcement',
      'title' => 'Test Announcement',
      'field_announcement_type' => 'alert',
    ];

    /** @var \Drupal\helfi_node_announcement\Entity\Announcement $node */
    $node = Announcement::create($values);
    $this->assertInstanceOf(Announcement::class, $node);
    $this->assertEquals('alert', $node->getAnnouncementType());

    $labels = $node->getLabels();
    $this->assertEquals('Alert', $labels['type']);
    $this->assertEquals('Close', $labels['close']);
  }

}
