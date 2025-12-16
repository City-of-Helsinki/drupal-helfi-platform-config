<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\Hooks;

use Drupal\Core\Field\Entity\BaseFieldOverride;
use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\ParagraphsType;

/**
 * Tests the entity hooks.
 */
final class EntityHooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
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
   * {@inheritdoc}
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
  public function testFieldConfigPresave(): void {
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

  /**
   * Tests the base field override presave hook for paragraphs library items.
   */
  public function testBaseFieldOverridePresave(): void {
    ParagraphsType::create(['id' => 'test_paragraph', 'label' => 'Test paragraph'])->save();
    ParagraphsType::create(['id' => 'not_used_paragraph', 'label' => 'Not used paragraph'])->save();

    $baseFieldOverride = BaseFieldOverride::create([
      'field_name' => 'paragraphs',
      'entity_type' => 'paragraphs_library_item',
      'bundle' => 'paragraphs_library_item',
      'label' => 'Paragraphs',
      'settings' => [
        'handler' => 'default:paragraph',
        'handler_settings' => [
          'target_bundles' => [],
          'target_bundles_drag_drop' => [],
        ],
      ],
    ]);

    $baseFieldOverride->save();

    $paragraphsLibraryItem = BaseFieldOverride::load('paragraphs_library_item.paragraphs_library_item.paragraphs');
    $this->assertNotNull($paragraphsLibraryItem);

    $handlerSettings = $paragraphsLibraryItem->getSetting('handler_settings');
    $this->assertSame('test_paragraph', $handlerSettings['target_bundles']['test_paragraph']);
    $this->assertSame(
      ['weight' => 0, 'enabled' => TRUE],
      $handlerSettings['target_bundles_drag_drop']['test_paragraph']
    );
    $this->assertArrayNotHasKey('not_used_paragraph', $handlerSettings['target_bundles']);
    $this->assertArrayNotHasKey('not_used_paragraph', $handlerSettings['target_bundles_drag_drop']);
  }

}
