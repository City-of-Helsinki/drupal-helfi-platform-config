<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_base_content\Kernel\Hook;

use Drupal\Core\Session\AccountInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\helfi_base_content\Entity\ParagraphFromLibrary;
use Drupal\helfi_base_content\Hook\ParagraphFromLibraryHooks;
use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs_library\Entity\LibraryItem;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the from_library paragraph access hook.
 *
 * @group helfi_base_content
 */
#[RunTestsInSeparateProcesses]
class ParagraphFromLibraryHooksTest extends KernelTestBase {

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
    ParagraphsType::create(['id' => 'text', 'label' => 'Text'])->save();

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
   * Creates a mock account with the given authentication status.
   */
  private function createAccount(bool $authenticated): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn($authenticated);

    return $account;
  }

  /**
   * Tests that anonymous users are denied view access to unpublished content.
   */
  public function testForbidsAnonymousUserFromUnpublishedContent(): void {
    $paragraph = $this->createParagraph(FALSE);

    $access = ParagraphFromLibraryHooks::paragraphAccess($paragraph, 'view', $this->createAccount(FALSE));

    $this->assertTrue($access->isForbidden());
  }

  /**
   * Tests that authenticated users are not affected by the access check.
   */
  public function testAllowsAuthenticatedUserForUnpublishedContent(): void {
    $paragraph = $this->createParagraph(FALSE);

    $access = ParagraphFromLibraryHooks::paragraphAccess($paragraph, 'view', $this->createAccount(TRUE));

    $this->assertTrue($access->isNeutral());
  }

}
