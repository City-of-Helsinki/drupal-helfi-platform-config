<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Kernel\Plugin\search_api;

use Drupal\node\Entity\Node;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\search_api\Kernel\Processor\ProcessorTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for the AddEntityLabel processor.
 */
#[Group('helfi_search')]
#[RunTestsInSeparateProcesses]
class AddEntityLabelTest extends ProcessorTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_platform_config',
    'config_rewrite',
    'helfi_api_base',
    'helfi_search',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp($processor = NULL): void {
    parent::setUp('helfi_entity_label');

    $field = new Field($this->index, 'label');
    $field->setType('string');
    $field->setPropertyPath('helfi_entity_label');
    $field->setLabel('Entity label');
    $this->index->addField($field);
    $this->index->save();
  }

  /**
   * Tests that the entity label is extracted into the field.
   */
  public function testAddFieldValues(): void {
    $node = Node::create([
      'title' => 'My Test Node',
      'type' => 'article',
    ]);
    $node->save();

    $id = Utility::createCombinedId('entity:node', $node->id() . ':en');
    $item = \Drupal::getContainer()
      ->get('search_api.fields_helper')
      ->createItemFromObject($this->index, $node->getTypedData(), $id);

    $this->assertEquals(['My Test Node'], $item->getField('label')->getValues());
  }

}
