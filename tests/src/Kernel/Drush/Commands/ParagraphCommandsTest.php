<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\helfi_platform_config\Drush\Commands\ParagraphCommands;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\ParagraphsType;

/**
 * Tests the ParagraphCommands Drush command class.
 *
 * Tests the paragraph scanning functionality with the
 * following test cases:
 * - Scan for orphaned paragraphs with empty database
 * - Scan and find actual orphaned paragraphs.
 *
 * @coversDefaultClass \Drupal\helfi_platform_config\Drush\Commands\ParagraphCommands
 * @group helfi_platform_config
 */
class ParagraphCommandsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_platform_config',
    'helfi_api_base',
    'paragraphs',
    'node',
    'user',
    'system',
    'field',
    'text',
    'file',
    'entity_reference_revisions',
    'config_rewrite',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    $this->installConfig(['field', 'paragraphs', 'node']);
  }

  /**
   * Tests scanning for orphaned paragraphs with empty database.
   *
   * @covers ::scan
   */
  public function testScanEmptyDatabase(): void {
    $entityTypeManager = $this->container->get('entity_type.manager');
    $connection = $this->container->get('database');

    $command = new ParagraphCommands($entityTypeManager, $connection);

    $result = $command->scan([
      'format' => 'table',
      'fix' => FALSE,
      'ids' => '',
      'fields' => 'id,parent_field_name,parent_type,parent_id,langcode',
    ]);

    $this->assertInstanceOf(RowsOfFields::class, $result);
    $rows = $result->getArrayCopy();
    $this->assertIsArray($rows);
    $this->assertEmpty($rows);
  }

  /**
   * Tests scanning for orphaned paragraphs when orphans exist.
   *
   * @covers ::scan
   */
  public function testScanFindsOrphanedParagraphs(): void {
    ParagraphsType::create([
      'id' => 'test_paragraph',
      'label' => 'Test Paragraph',
    ])->save();

    NodeType::create([
      'type' => 'test_page',
      'name' => 'Test Page',
    ])->save();

    $fieldStorage = FieldStorageConfig::create([
      'field_name' => 'field_paragraphs',
      'entity_type' => 'node',
      'type' => 'entity_reference_revisions',
      'settings' => [
        'target_type' => 'paragraph',
      ],
    ]);
    $fieldStorage->save();

    $fieldConfig = FieldConfig::create([
      'field_storage' => $fieldStorage,
      'bundle' => 'test_page',
      'label' => 'Paragraphs',
      'settings' => [
        'handler_settings' => [
          'target_bundles' => [
            'test_paragraph' => 'test_paragraph',
          ],
        ],
      ],
    ]);
    $fieldConfig->save();

    $node = Node::create([
      'type' => 'test_page',
      'title' => 'Test Node',
    ]);
    $node->save();

    // Simulate orphaned paragraph: paragraph exists in database with parent
    // references, but parent entity doesn't reference it back.
    $connection = $this->container->get('database');
    $connection->insert('paragraphs_item_field_data')
      ->fields([
        'id' => 100,
        'revision_id' => 100,
        'type' => 'test_paragraph',
        'parent_id' => $node->id(),
        'parent_type' => 'node',
        'parent_field_name' => 'field_paragraphs',
        'langcode' => 'en',
        'default_langcode' => 1,
        'status' => 1,
        'created' => \Drupal::time()->getCurrentTime(),
      ])
      ->execute();

    $entityTypeManager = $this->container->get('entity_type.manager');

    $command = new ParagraphCommands($entityTypeManager, $connection);

    $result = $command->scan([
      'format' => 'table',
      'fix' => FALSE,
      'ids' => '',
      'fields' => 'id,parent_field_name,parent_type,parent_id,langcode',
    ]);

    $this->assertInstanceOf(RowsOfFields::class, $result);
    $rows = $result->getArrayCopy();
    $this->assertIsArray($rows);
    $this->assertNotEmpty($rows, 'Should find orphaned paragraphs');
    $this->assertCount(1, $rows, 'Should find exactly one orphaned paragraph');

    $orphanRow = reset($rows);
    $this->assertEquals(100, $orphanRow['id']);
    $this->assertEquals('field_paragraphs', $orphanRow['parent_field_name']);
    $this->assertEquals('node', $orphanRow['parent_type']);
  }

}
