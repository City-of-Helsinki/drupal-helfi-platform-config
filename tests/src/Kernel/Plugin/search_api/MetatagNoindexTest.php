<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\Plugin\search_api;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\search_api\Kernel\Processor\ProcessorTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for the MetatagNoindex processor.
 */
#[Group('helfi_platform_config')]
#[RunTestsInSeparateProcesses]
class MetatagNoindexTest extends ProcessorTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_platform_config',
    'config_rewrite',
    'helfi_api_base',
    'metatag',
    'token',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp($processor = NULL): void {
    parent::setUp('helfi_metatag_noindex');

    $this->installConfig(['metatag']);

    NodeType::create(['type' => 'page'])->save();

    // Attach a metatag field to the node bundle so editors can override tags.
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_metatags',
      'type' => 'metatag',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_metatags',
      'bundle' => 'page',
    ])->save();

    $this->index->save();
  }

  /**
   * Tests that entities with the robots "noindex" directive are excluded.
   *
   * @param string|null $robots
   *   The robots metatag value to set on the node, or NULL to leave it unset.
   * @param bool $expectedKept
   *   Whether the item is expected to remain in the index.
   */
  #[DataProvider('noindexDataProvider')]
  public function testAlterIndexedItems(?string $robots, bool $expectedKept): void {
    $values = ['type' => 'page', 'title' => 'Test node'];
    if ($robots !== NULL) {
      $values['field_metatags'] = ['value' => json_encode(['robots' => $robots])];
    }
    $node = Node::create($values);
    $node->save();

    $items = $this->generateItems([
      [
        'datasource' => 'entity:node',
        'item' => $node->getTypedData(),
        'item_id' => $node->id() . ':en',
      ],
    ]);

    $this->processor->alterIndexedItems($items);

    $this->assertSame($expectedKept, $items !== []);
  }

  /**
   * Data provider for testAlterIndexedItems().
   *
   * @return array<string, array{string|null, bool}>
   *   Sets of [robots metatag value, whether the item should be kept].
   */
  public static function noindexDataProvider(): array {
    return [
      'no robots tag is kept' => [NULL, TRUE],
      'noindex is removed' => ['noindex', FALSE],
    ];
  }

}
