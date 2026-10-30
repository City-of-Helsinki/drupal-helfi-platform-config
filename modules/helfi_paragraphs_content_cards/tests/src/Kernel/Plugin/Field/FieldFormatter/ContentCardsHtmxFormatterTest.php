<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_content_cards\Kernel\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\helfi_paragraphs_content_cards\Plugin\Field\FieldFormatter\ContentCardsHtmxFormatter;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\Tests\helfi_platform_config\Kernel\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the content cards HTMX placeholder.
 */
#[Group('helfi_paragraphs_content_cards')]
#[RunTestsInSeparateProcesses]
final class ContentCardsHtmxFormatterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'file',
    'paragraphs',
    'entity_reference_revisions',
    'helfi_paragraphs_content_cards',
  ];

  /**
   * The formatter under test.
   */
  private ContentCardsHtmxFormatter $formatter;

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');

    NodeType::create([
      'type' => 'page',
      'name' => 'Page',
    ])->save();

    ParagraphsType::create([
      'id' => 'content_cards',
      'label' => 'Content cards',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_content_cards_content_ext',
      'entity_type' => 'paragraph',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'node',
      ],
      'cardinality' => -1,
    ])->save();

    $field = FieldConfig::create([
      'field_name' => 'field_content_cards_content_ext',
      'entity_type' => 'paragraph',
      'bundle' => 'content_cards',
      'label' => 'Content',
    ]);
    $field->save();

    $formatter = $this->container->get('plugin.manager.field.formatter')->createInstance('content_cards_htmx', [
      'field_definition' => $field,
      'settings' => [],
      'label' => 'hidden',
      'view_mode' => 'default',
      'third_party_settings' => [],
    ]);
    $this->assertInstanceOf(ContentCardsHtmxFormatter::class, $formatter);
    $this->formatter = $formatter;
  }

  /**
   * The placeholder renders the content cards.
   */
  public function testPlaceholderRenders() : void {
    $first = Node::create([
      'type' => 'page',
      'title' => 'First',
    ]);
    $first->save();
    $second = Node::create([
      'type' => 'page',
      'title' => 'Second',
    ]);
    $second->save();

    $paragraph = Paragraph::create([
      'type' => 'content_cards',
      'field_content_cards_content_ext' => [
        ['target_id' => $first->id()],
        ['target_id' => $second->id()],
      ],
    ]);
    $paragraph->save();

    $elements = $this->formatter->viewElements($this->items($paragraph), 'fi');

    $this->assertCount(1, $elements);
    $this->assertSame('helfi_htmx_preview__content_cards', $elements[0]['#theme']);
    $this->assertSame(2, $elements[0]['#num_items']);
  }

  /**
   * An empty field renders nothing.
   */
  public function testEmptyFieldRendersNothing() : void {
    $paragraph = Paragraph::create([
      'type' => 'content_cards',
    ]);
    $paragraph->save();

    $this->assertSame([], $this->formatter->viewElements($this->items($paragraph), 'fi'));
  }

  /**
   * Returns the content cards entity reference field.
   *
   * @phpstan-return \Drupal\Core\Field\EntityReferenceFieldItemListInterface<\Drupal\Core\Entity\EntityInterface>
   */
  private function items(Paragraph $paragraph) : EntityReferenceFieldItemListInterface {
    $items = $paragraph->get('field_content_cards_content_ext');
    if (!$items instanceof EntityReferenceFieldItemListInterface) {
      throw new \LogicException('The content cards field is not an entity reference.');
    }
    return $items;
  }

}
