<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Kernel;

use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\helfi_platform_config\TextConverter\TextConverterManager;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Proves the summary feature can render *unsaved* form state.
 *
 * The AI summary widget builds the entity from the current edit form
 * (ContentEntityForm::buildEntity()), marks it in_preview, and hands it to the
 * generator, which renders it via the text_converter view mode. This test
 * locks in the underlying capability: an unsaved / in-memory-edited node is
 * converted to text from its *current* values, not the persisted ones.
 *
 * @group helfi_ai
 */
class UnsavedTextConversionTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_platform_config',
    'helfi_api_base',
    'config_rewrite',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    NodeType::create([
      'name' => 'Test',
      'type' => 'test_node_bundle',
    ])->save();

    DateFormat::create([
      'id' => 'fallback',
      'pattern' => 'D, m/d/Y - H:i',
      'label' => 'Fallback',
    ])->save();

    // The text converter renders this view mode.
    EntityViewMode::create([
      'id' => 'node.text_converter',
      'targetEntityType' => 'node',
      'status' => TRUE,
      'label' => 'Text converter',
    ])->save();
    EntityViewDisplay::create([
      'id' => 'node.test_node_bundle.text_converter',
      'targetEntityType' => 'node',
      'bundle' => 'test_node_bundle',
      'mode' => 'text_converter',
      'status' => TRUE,
    ])->save();
  }

  /**
   * A brand-new, never-saved node is converted from its in-memory values.
   */
  public function testConvertsUnsavedNewNode(): void {
    $title = 'Unsaved new node title ' . $this->randomMachineName();

    $node = Node::create([
      'type' => 'test_node_bundle',
      'title' => $title,
    ]);
    // Never saved: this is what buildEntity() yields for a new node.
    $this->assertTrue($node->isNew());
    $node->in_preview = TRUE;

    $converted = $this->container->get(TextConverterManager::class)->convert($node);

    $this->assertNotNull($converted);
    $this->assertStringContainsString($title, $converted);
  }

  /**
   * An existing node with unsaved in-memory edits is converted from the edits.
   *
   * Guards the in_preview = TRUE on existing entities: without it the entity
   * view builder could return the cached, saved render instead of the edits.
   */
  public function testConvertsUnsavedEditsOnExistingNode(): void {
    $savedTitle = 'Saved title ' . $this->randomMachineName();
    $node = Node::create([
      'type' => 'test_node_bundle',
      'title' => $savedTitle,
    ]);
    $node->save();

    // Render once while saved so any render cache is primed with the old value.
    $manager = $this->container->get(TextConverterManager::class);
    $this->assertStringContainsString($savedTitle, (string) $manager->convert($node));

    // Edit in memory only — do not save.
    $editedTitle = 'Edited but unsaved ' . $this->randomMachineName();
    $node->setTitle($editedTitle);
    $node->in_preview = TRUE;

    $converted = (string) $manager->convert($node);

    $this->assertStringContainsString($editedTitle, $converted);
    $this->assertStringNotContainsString($savedTitle, $converted);
  }

}
