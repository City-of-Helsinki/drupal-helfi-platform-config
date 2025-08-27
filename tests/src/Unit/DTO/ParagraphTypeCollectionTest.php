<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\DTO;

use Drupal\helfi_platform_config\DTO\ParagraphTypeCollection;
use PHPUnit\Framework\TestCase;

/**
 * Tests the ParagraphTypeCollection DTO class.
 *
 * Tests the paragraph type collection data object with the
 * following test cases:
 * - Constructor property assignment with custom weight
 * - Constructor property assignment with default weight.
 *
 * @coversDefaultClass \Drupal\helfi_platform_config\DTO\ParagraphTypeCollection
 * @group helfi_platform_config
 */
class ParagraphTypeCollectionTest extends TestCase {

  /**
   * Tests constructor property assignment with custom weight.
   *
   * @covers ::__construct
   */
  public function testConstructorAssignsProperties(): void {
    $entityType = 'node';
    $bundle = 'page';
    $field = 'field_paragraphs';
    $paragraph = 'text_paragraph';
    $weight = 10;

    $collection = new ParagraphTypeCollection(
      $entityType,
      $bundle,
      $field,
      $paragraph,
      $weight
    );

    $this->assertEquals($entityType, $collection->entityType);
    $this->assertEquals($bundle, $collection->bundle);
    $this->assertEquals($field, $collection->field);
    $this->assertEquals($paragraph, $collection->paragraph);
    $this->assertEquals($weight, $collection->weight);
    $this->assertIsString($collection->entityType);
    $this->assertIsInt($collection->weight);
  }

  /**
   * Tests constructor property assignment with default weight.
   *
   * @covers ::__construct
   */
  public function testConstructorDefaultWeight(): void {
    $entityType = 'node';
    $bundle = 'page';
    $field = 'field_paragraphs';
    $paragraph = 'text_paragraph';

    $collection = new ParagraphTypeCollection(
      $entityType,
      $bundle,
      $field,
      $paragraph
    );

    $this->assertEquals(0, $collection->weight, 'Default weight should be 0');
    $this->assertEquals($entityType, $collection->entityType);
    $this->assertEquals($bundle, $collection->bundle);
    $this->assertEquals($field, $collection->field);
    $this->assertEquals($paragraph, $collection->paragraph);
    $this->assertIsInt($collection->weight, 'Weight should be integer type');
  }

}
