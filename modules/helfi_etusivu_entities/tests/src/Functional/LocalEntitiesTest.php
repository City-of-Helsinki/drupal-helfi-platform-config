<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_etusivu_entities\Functional;

use Drupal\node\NodeInterface;
use Drupal\Tests\block\Traits\BlockCreationTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\user\Entity\Role;

/**
 * Tests blocks.
 *
 * @group helfi_platform_config
 */
final class LocalEntitiesTest extends BrowserTestBase {

  use NodeCreationTrait;
  use BlockCreationTrait;

  /**
   * Modules to enable.
   *
   * @var string[]
   */
  protected static $modules = [
    'helfi_etusivu_entities',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Test node.
   */
  private NodeInterface $testNode;

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->testNode = $this->createNode([
      'status' => NodeInterface::PUBLISHED,
      'type' => 'page',
      'title' => 'Test node',
      'langcode' => 'en',
    ]);

    $this->placeBlock('surveys', [
      'use_remote_entities' => FALSE,
    ]);

    $this->placeBlock('announcements', [
      'use_remote_entities' => FALSE,
    ]);

    $this->grantPermissions(Role::load('anonymous'), ['access content']);
  }

  /**
   * Tests blocks.
   */
  public function testBlocks(): void {
    $this->createNode([
      'status' => NodeInterface::PUBLISHED,
      'type' => 'survey',
      'title' => 'Old test survey',
      'body' => 'Old survey content',
      'langcode' => 'en',
      // Hide this if there is a survey published after 2000-01-01.
      'published_at' => 946677600,
      'field_survey_link' => 'https://example.com',
    ]);

    // Only the newest survey is shown.
    $this->createNode([
      'status' => NodeInterface::PUBLISHED,
      'type' => 'survey',
      'title' => 'New test survey',
      'body' => 'New survey content',
      'langcode' => 'en',
      'field_survey_link' => 'https://example.com',
      // Only show this on test node.
      'field_survey_content_pages' => [
        [
          'target_id' => $this->testNode->id(),
        ],
      ],
    ]);

    $this->createNode([
      'status' => NodeInterface::PUBLISHED,
      'type' => 'announcement',
      'title' => 'Test announcement',
      'body' => 'Announcement content',
      'langcode' => 'en',
    ]);

    $this->drupalGet('/');

    $this->assertSession()->pageTextContains('Old test survey');
    $this->assertSession()->pageTextNotContains('New test survey');
    $this->assertSession()->pageTextContains('Test announcement');

    $this->drupalGet($this->testNode->toUrl());

    $this->assertSession()->pageTextContains('New test survey');
    $this->assertSession()->pageTextNotContains('Old test survey');
    $this->assertSession()->pageTextContains('Test announcement');
  }

}