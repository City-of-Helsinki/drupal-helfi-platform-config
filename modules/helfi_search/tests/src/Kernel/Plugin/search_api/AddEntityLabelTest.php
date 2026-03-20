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

    $labelField = new Field($this->index, 'label');
    $labelField->setType('string');
    $labelField->setPropertyPath('helfi_entity_label');
    $labelField->setLabel('Entity label');
    $this->index->addField($labelField);

    $bundleField = new Field($this->index, 'entity_bundle');
    $bundleField->setType('string');
    $bundleField->setPropertyPath('helfi_entity_bundle');
    $bundleField->setLabel('Entity bundle');
    $this->index->addField($bundleField);

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
    $this->assertEquals(['article'], $item->getField('entity_bundle')->getValues());
  }

}
