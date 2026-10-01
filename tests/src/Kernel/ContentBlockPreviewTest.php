<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\helfi_platform_config\EntityVersionMatcher;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use PHPUnit\Framework\Attributes\DataProvider;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests the paragraph entities on content blocks when node is previewed.
 *
 * @group helfi_platform_config
 */
class ContentBlockPreviewTest extends KernelTestBase {

  use ProphecyTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'entity_reference_revisions',
    'field',
    'file',
    'node',
    'paragraphs',
    'system',
    'text',
    'twig_tweak',
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
    $this->installConfig(['node']);

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    ParagraphsType::create(['id' => 'text', 'label' => 'Text'])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_text',
      'entity_type' => 'paragraph',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_text',
      'entity_type' => 'paragraph',
      'bundle' => 'text',
    ])->save();
    EntityViewDisplay::create([
      'targetEntityType' => 'paragraph',
      'bundle' => 'text',
      'mode' => 'default',
      'status' => TRUE,
    ])->setComponent('field_text', ['type' => 'string'])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_has_hero',
      'entity_type' => 'node',
      'type' => 'boolean',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_has_hero',
      'entity_type' => 'node',
      'bundle' => 'page',
    ])->save();

    foreach (['field_lower_content', 'field_hero'] as $field_name) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'type' => 'entity_reference_revisions',
        'settings' => ['target_type' => 'paragraph'],
        'cardinality' => -1,
      ])->save();
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'bundle' => 'page',
      ])->save();
    }
  }

  /**
   * Tests that unsaved paragraphs render on the node preview.
   */
  #[DataProvider('getBlocks')]
  public function testUnsavedParagraphs(string $plugin_id, string $field_name): void {
    $paragraph = Paragraph::create([
      'type' => 'text',
      'field_text' => 'Unsaved paragraph',
    ]);
    $node = Node::create([
      'type' => 'page',
      'title' => 'Preview',
      'field_has_hero' => TRUE,
      $field_name => [$paragraph],
    ]);

    $versionMatcher = $this->prophesize(EntityVersionMatcher::class);
    $versionMatcher->getType()->willReturn([
      'entity' => $node,
      'entity_version' => EntityVersionMatcher::ENTITY_VERSION_PREVIEW,
    ]);
    $this->container->set('helfi_platform_config.entity_version_matcher', $versionMatcher->reveal());

    $block = $this->container->get('plugin.manager.block')
      ->createInstance($plugin_id);
    $this->assertInstanceOf(BlockPluginInterface::class, $block);
    $build = $block->build();
    $output = (string) $this->container->get('renderer')->renderInIsolation($build);

    $this->assertStringContainsString('Unsaved paragraph', $output, 'The unsaved paragraph is rendered.');
  }

  /**
   * Data provider for the content blocks.
   *
   * @return array<string, array{string, string}>
   *   The block plugin ids and paragraph field names.
   */
  public static function getBlocks(): array {
    return [
      'lower content' => ['lower_content_block', 'field_lower_content'],
      'hero' => ['hero_block', 'field_hero'],
    ];
  }

}
