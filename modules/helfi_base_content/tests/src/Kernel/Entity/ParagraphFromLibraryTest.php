<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_base_content\Kernel\Entity;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\helfi_base_content\Entity\ParagraphFromLibrary;
use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs_library\Entity\LibraryItem;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the from_library paragraph bundle class.
 *
 * @group helfi_base_content
 */
#[RunTestsInSeparateProcesses]
class ParagraphFromLibraryTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_reference_revisions',
    'field',
    'file',
    'filter',
    'helfi_base_content',
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
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('paragraphs_type');
    $this->installEntitySchema('paragraphs_library_item');
    $this->installEntitySchema('field_storage_config');
    $this->installEntitySchema('field_config');

    ParagraphsType::create(['id' => 'from_library', 'label' => 'From library'])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_reusable_paragraph',
      'entity_type' => 'paragraph',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'paragraphs_library_item',
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_reusable_paragraph',
      'entity_type' => 'paragraph',
      'bundle' => 'from_library',
      'settings' => [
        'handler' => 'default:paragraphs_library_item',
      ],
    ])->save();
  }

  /**
   * Creates a from_library paragraph referencing a library item.
   */
  private function createParagraph(bool $libraryItemPublished): ParagraphFromLibrary {
    $libraryItem = LibraryItem::create([
      'label' => 'Test library item',
      'status' => $libraryItemPublished,
    ]);
    $libraryItem->save();

    /** @var \Drupal\helfi_base_content\Entity\ParagraphFromLibrary $paragraph */
    $paragraph = ParagraphFromLibrary::create([
      'type' => 'from_library',
      'field_reusable_paragraph' => ['target_id' => $libraryItem->id()],
    ]);
    $paragraph->save();

    return $paragraph;
  }

  /**
   * Tests isNotPublished() with a published library item.
   */
  public function testIsNotPublishedWithPublishedLibraryItem(): void {
    $paragraph = $this->createParagraph(TRUE);

    $this->assertInstanceOf(ParagraphFromLibrary::class, $paragraph);
    $this->assertFalse($paragraph->isNotPublished());
  }

  /**
   * Tests isNotPublished() with an unpublished library item.
   */
  public function testIsNotPublishedWithUnpublishedLibraryItem(): void {
    $paragraph = $this->createParagraph(FALSE);

    $this->assertTrue($paragraph->isNotPublished());
  }

  /**
   * Tests isNotPublished() without a referenced library item.
   */
  public function testIsNotPublishedWithoutReference(): void {
    $paragraph = ParagraphFromLibrary::create(['type' => 'from_library']);
    $paragraph->save();

    $this->assertFalse($paragraph->isNotPublished());
  }

}
