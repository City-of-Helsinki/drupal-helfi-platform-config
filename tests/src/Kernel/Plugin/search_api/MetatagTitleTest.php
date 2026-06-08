<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\Plugin\search_api;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\search_api\Kernel\Processor\ProcessorTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for the MetatagTitle processor.
 */
#[Group('helfi_platform_config')]
#[RunTestsInSeparateProcesses]
class MetatagTitleTest extends ProcessorTestBase {

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
    parent::setUp('helfi_metatag_title');

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

    $titleField = new Field($this->index, 'metatag_title');
    $titleField->setType('string');
    $titleField->setPropertyPath('helfi_search_title');
    $titleField->setLabel('Metatag title');
    $this->index->addField($titleField);

    $this->index->save();
  }

  /**
   * Tests that the metatag title is resolved into the field.
   *
   * @param array<string, mixed> $values
   *   The node field values to create the test node with.
   * @param string $expected
   *   The expected indexed title value.
   */
  #[DataProvider('titleDataProvider')]
  public function testAddFieldValues(array $values, string $expected): void {
    $node = Node::create(array_merge(['type' => 'page'], $values));
    $node->save();

    $item = $this->createNodeItem($node);

    $this->assertEquals([$expected], $item->getField('metatag_title')->getValues());
  }

  /**
   * Data provider for testAddFieldValues().
   *
   * @return array<string, array{array<string, mixed>, string}>
   *   Sets of [metatag title template, expected indexed title].
   */
  public static function titleDataProvider(): array {
    return [
      'customized title strips token and separator' => [
        [
          'title' => 'Original title',
          'field_metatags' => [
            'value' => json_encode(['title' => 'Custom title | Foobar | [site:page-title-suffix]']),
          ],
        ],
        'Custom title | Foobar',
      ],
      'falls back to entity label when not customized' => [
        ['title' => 'Original title'],
        'Original title',
      ],
    ];
  }

  /**
   * Creates a search api item for the given node.
   *
   * @phpstan-return \Drupal\search_api\Item\ItemInterface<mixed>
   */
  private function createNodeItem(Node $node): ItemInterface {
    $id = Utility::createCombinedId('entity:node', $node->id() . ':en');

    return $this->container
      ->get('search_api.fields_helper')
      ->createItemFromObject($this->index, $node->getTypedData(), $id);
  }

}
