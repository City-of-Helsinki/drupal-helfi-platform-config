<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ckeditor\Kernel\Drush\Commands;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\helfi_ckeditor\Drush\Commands\EmptyPTagCommand;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Tests the empty p tag removal command.
 */
#[Group('helfi_ckeditor')]
class EmptyPTagCommandTest extends KernelTestBase {

  /**
   * The content with every empty p tag variant.
   */
  private const string DIRTY = "<p>Some text </p><p> </p><p></p><p>&nbsp;</p><p>  &nbsp; </p><p>\u{00A0}</p><p> &nbsp; \u{00A0} </p><p>Some other text.</p>";

  /**
   * The content without the empty p tags.
   */
  private const string CLEAN = '<p>Some text </p><p>Some other text.</p>';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'filter',
    'text',
  ];

  /**
   * The command output.
   */
  private BufferedOutput $output;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['field', 'filter', 'node']);

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();

    foreach (['field_text' => 'text_long', 'field_plain' => 'string_long'] as $name => $type) {
      FieldStorageConfig::create([
        'field_name' => $name,
        'entity_type' => 'node',
        'type' => $type,
      ])->save();

      FieldConfig::create([
        'field_name' => $name,
        'entity_type' => 'node',
        'bundle' => 'page',
        'label' => $name,
      ])->save();
    }

    $this->output = new BufferedOutput();
  }

  /**
   * Runs the command.
   *
   * @return int
   *   Returns the exit code.
   */
  private function runCommand(): int {
    $command = new EmptyPTagCommand(
      $this->container->get('entity_type.manager'),
      $this->container->get('database'),
    );

    return $command->run(new ArrayInput([]), $this->output);
  }

  /**
   * Creates a page with the given field value.
   *
   * @param string $value
   *   The field value.
   * @param int|null $id
   *   The node ID.
   *
   * @return \Drupal\node\NodeInterface
   *   Returns the created node.
   */
  private function createPage(string $value, ?int $id = NULL): NodeInterface {
    $node = Node::create([
      'nid' => $id,
      'type' => 'page',
      'title' => 'Test page',
      'field_text' => $value,
      'field_plain' => $value,
    ]);
    $node->save();

    return $node;
  }

  /**
   * Gets a stored field value directly from the database.
   *
   * @param string $table
   *   The field table.
   * @param string $column
   *   The value column.
   * @param string $key
   *   The ID column.
   * @param int|string $id
   *   The ID.
   *
   * @return string
   *   Returns the stored value.
   */
  private function getStoredValue(string $table, string $column, string $key, int|string $id): string {
    return $this->container->get('database')
      ->select($table, 't')
      ->fields('t', [$column])
      ->condition($key, $id)
      ->execute()
      ->fetchField();
  }

  /**
   * Tests that empty p tags are removed from the current long text values.
   */
  public function testRemovesEmptyTags(): void {
    // Skip the empty tables.
    $this->assertSame(Command::SUCCESS, $this->runCommand());
    $this->assertStringContainsString('node__field_text: updated 0 rows.', $this->output->fetch());

    $revisioned = $this->createPage(self::DIRTY, 1);
    $oldRevisionId = $revisioned->getRevisionId();
    $revisioned->setNewRevision();
    $revisioned->set('field_text', self::DIRTY . self::DIRTY);
    $revisioned->save();

    $clean = $this->createPage(self::CLEAN, 2);

    // Spread the nodes over three batches.
    $batched = [
      $this->createPage(self::DIRTY, 1500),
      $this->createPage(self::DIRTY, 2500),
    ];

    $this->assertSame(Command::SUCCESS, $this->runCommand());

    $output = $this->output->fetch();
    $this->assertStringContainsString('node__field_text: updated 3 rows.', $output, 'Only the dirty data rows are updated.');
    $this->assertStringContainsString('node_revision__field_text: updated 3 rows.', $output, 'Only the current dirty revisions are updated.');

    $this->assertSame(self::CLEAN . self::CLEAN, $this->getStoredValue('node__field_text', 'field_text_value', 'entity_id', $revisioned->id()), 'Empty p tags are removed from the data table.');
    $this->assertSame(self::CLEAN . self::CLEAN, $this->getStoredValue('node_revision__field_text', 'field_text_value', 'revision_id', $revisioned->getRevisionId()), 'Empty p tags are removed from the current revision.');
    $this->assertSame(self::DIRTY, $this->getStoredValue('node_revision__field_text', 'field_text_value', 'revision_id', $oldRevisionId), 'The old revision is left unchanged.');
    $this->assertSame(self::DIRTY, $this->getStoredValue('node__field_plain', 'field_plain_value', 'entity_id', $revisioned->id()), 'The plain text field is left unchanged.');
    $this->assertSame(self::CLEAN, $this->getStoredValue('node__field_text', 'field_text_value', 'entity_id', $clean->id()), 'Clean content is left unchanged.');

    foreach ($batched as $node) {
      $this->assertSame(self::CLEAN, $this->getStoredValue('node__field_text', 'field_text_value', 'entity_id', $node->id()), "Empty p tags are removed from node {$node->id()}.");
    }
  }

}
