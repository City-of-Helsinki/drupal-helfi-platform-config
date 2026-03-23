<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\Plugin\search_api;

use Drupal\node\Entity\Node;
use Drupal\search_api\Item\Field;
use Drupal\Tests\search_api\Kernel\Processor\ProcessorTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for the ResolveLinkUri processor.
 */
#[Group('helfi_platform_config')]
#[RunTestsInSeparateProcesses]
class ResolveLinkUriTest extends ProcessorTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_platform_config',
    'config_rewrite',
    'helfi_api_base',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp($processor = NULL): void {
    parent::setUp('helfi_resolve_link_uri');

    $linkField = new Field($this->index, 'link_uri');
    $linkField->setType('string');
    $linkField->setPropertyPath('link_uri');
    $linkField->setDatasourceId('entity:node');
    $linkField->setLabel('Link URI');
    $this->index->addField($linkField);

    $this->processor->setConfiguration([
      'fields' => ['link_uri'],
    ]);
  }

  /**
   * Tests that an internal URI is resolved to an absolute URL.
   */
  public function testInternalUri(): void {
    $items = $this->createItemsWithUri('internal:/node/1');

    $this->processor->preprocessIndexItems($items);
    $resolved = array_first($items)->getField('link_uri')->getValues()[0];

    // The URI is resolved from 'internal:/node/1' to a path.
    $this->assertStringContainsString('/node/1', $resolved);
    $this->assertStringStartsNotWith('internal:', $resolved);

    $items = $this->createItemsWithUri('https://example.com/page');

    $this->processor->preprocessIndexItems($items);
    $resolved = array_first($items)->getField('link_uri')->getValues()[0];

    // External URL.
    $this->assertEquals('https://example.com/page', $resolved);
  }

  /**
   * Creates search API items with a given link URI value.
   */
  private function createItemsWithUri(string $uri): array {
    $node = Node::create([
      'title' => 'Test',
      'type' => 'article',
    ]);
    $node->save();

    return $this->generateItems([
      [
        'datasource' => 'entity:node',
        'item' => $node->getTypedData(),
        'item_id' => $node->id() . ':en',
        'link_uri' => $uri,
      ],
    ]);
  }

}
