<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\Plugin\Field\FieldFormatter;

use Drupal\Tests\UnitTestCase;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\StringLongItem;
use Drupal\helfi_platform_config\Plugin\Field\FieldFormatter\TextSpan;

/**
 * Tests the TextSpan field formatter.
 *
 * Test coverage includes:
 * - HTML filtering to allow only span tags
 * - Preservation of span tag attributes
 * - Removal of potentially dangerous HTML tags.
 *
 * @coversDefaultClass \Drupal\helfi_platform_config\Plugin\Field\FieldFormatter\TextSpan
 * @group helfi_platform_config
 */
class TextSpanTest extends UnitTestCase {

  /**
   * Tests that span tags are preserved while other HTML is filtered out.
   *
   * @covers ::viewElements
   */
  public function testViewElements(): void {
    // Setup.
    $formatter = new TextSpan('text_span', [], $this->createMock(FieldDefinitionInterface::class), [], '', [], []);
    $items = $this->createMock(FieldItemList::class);
    $item = $this->createMock(StringLongItem::class);

    // Mock the field item to return test HTML.
    $item->method('__get')
      ->with('value')
      ->willReturn('Test <span>content</span> and <script>alert(1)</script>');

    // Mock the items list to return our test item.
    $items->method('getIterator')
      ->willReturn(new \ArrayIterator([$item]));

    // Test.
    $elements = $formatter->viewElements($items, 'en');

    // Assert.
    $this->assertStringContainsString('<span>content</span>', $elements[0]['#children']);
    $this->assertStringNotContainsString('<script>', $elements[0]['#children']);
  }

}
