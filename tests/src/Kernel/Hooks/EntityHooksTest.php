<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\ParagraphsType;

/**
 *
 */
final class EntityHooksTest extends KernelTestBase {

  protected static $modules = [
    'config_rewrite',
    'entity_reference_revisions',
    'field',
    'file',
    'filter',
    'helfi_api_base',
    'helfi_platform_config',
    'helfi_platform_config_update_test',
    'node',
    'paragraphs',
    'paragraphs_library',
    'system',
    'text',
    'user',
  ];

  /**
   *
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('paragraphs_type');
    $this->installEntitySchema('paragraphs_library_item');
    $this->installEntitySchema('base_field_override');
    $this->installEntitySchema('field_storage_config');
    $this->installEntitySchema('field_config');

    $this->installConfig(['node']);

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    ParagraphsType::create(['id' => 'text', 'label' => 'Text'])->save();
    ParagraphsType::create(['id' => 'image', 'label' => 'Image'])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_paragraphs',
      'entity_type' => 'node',
      'type' => 'entity_reference_revisions',
      'settings' => [
        'target_type' => 'paragraph',
      ],
      'cardinality' => -1,
    ])->save();
  }

  /**
   * Tests the field config presave hook.
   */
  protected function testFieldConfigPresave(): void {
    $field = FieldConfig::create([
      'field_name' => 'field_paragraphs',
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => 'Paragraphs',
      'settings' => [
        'handler' => 'default:paragraph',
        'handler_settings' => [
          'target_bundles' => [],
          'target_bundles_drag_drop' => [],
        ],
      ],
    ]);

    $field->save();

    $fieldConfig = FieldConfig::load('node.page.field_paragraphs');
    $this->assertNotNull($fieldConfig);

    $handlerSettings = $fieldConfig->getSetting('handler_settings');

    $this->assertSame('text', $handlerSettings['target_bundles']['text']);
    $this->assertSame('image', $handlerSettings['target_bundles']['image']);
    $this->assertSame(['weight' => 1, 'enabled' => TRUE], $handlerSettings['target_bundles_drag_drop']['text']);
    $this->assertSame(['weight' => 2, 'enabled' => TRUE], $handlerSettings['target_bundles_drag_drop']['image']);
  }

}
