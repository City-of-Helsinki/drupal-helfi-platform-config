<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_users\Functional;

use Drupal\Core\Database\Database;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\UserInterface;

/**
 * Tests for reassigning node revisions when canceling users.
 */
class UserCancelNodeRevisionsTest extends KernelTestBase {

  use NodeCreationTrait;
  use UserCreationTrait;

  /**
   * Uid 1 user.
   */
  protected UserInterface $admin;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'user',
    'system',
    'helfi_users',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    foreach (['node', 'user'] as $entityTypeId) {
      $this->installEntitySchema($entityTypeId);
    }

    $this->installSchema('node', 'node_access');

    $this->admin = $this->createUser(name: 'admin', values: [
      'uid' => 1,
    ]);
  }

  /**
   * Tests revision reassign function.
   */
  public function testRevisionAnonymization(): void {
    $testUser = $this->createUser(name: 'testuser');
    $node = $this->createNode([
      'uid' => $testUser->id(),
    ]);

    // Create few revisions for a total of 4 including the original.
    $revision_count = 3;
    for ($i = 0; $i < $revision_count; $i++) {
      $node->setNewRevision();
      $node
        ->setTitle($this->randomMachineName())
        ->save();
    }

    // Run function for test user, assign content to .
    _helfi_users_reassign_nodes($testUser, $this->admin);

    // Test that revisions for this user were anonymized correctly.
    $connection = Database::getConnection();
    $revision_count = $connection->select('node_revision')
      ->condition('revision_uid', $testUser->id())
      ->condition('nid', $node->id())
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertEquals(0, (int) $revision_count, 'Found revisions after running anonymization function.');

    // Test that the revisions were correctly assigned to target user.
    $anon_revision_count = $connection->select('node_revision')
      ->condition('revision_uid', $this->admin->id())
      ->condition('nid', $node->id())
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertEquals(4, (int) $anon_revision_count, 'Amount of anonymized revisions does not match');
  }

}
