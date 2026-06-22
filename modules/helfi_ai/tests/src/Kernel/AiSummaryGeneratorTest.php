<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Kernel;

use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\helfi_ai\Service\AiSummaryGenerator;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests AI summary generation through the real AI provider stack.
 *
 * Runs the generator against the AI module's echoai test provider, so the whole
 * chain is exercised for real (provider resolution, prompt building, the chat
 * call, and HTML bullet conversion) without any external service or API key.
 * The echoai provider echoes the prompt back, which the generator wraps as a
 * bullet list.
 */
#[Group('helfi_ai')]
#[RunTestsInSeparateProcesses]
class AiSummaryGeneratorTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_platform_config',
    'helfi_api_base',
    'config_rewrite',
    'node',
    'language',
    'key',
    'ai',
    'ai_test',
    'helfi_ai',
  ];

  /**
   * The summary generator under test.
   */
  private AiSummaryGenerator $generator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['ai', 'ai_test', 'helfi_ai']);
    $this->installEntitySchema('ai_mock_provider_result');

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

    // Resolve chat operations to the echoai test provider.
    $this->config('ai.settings')
      ->set('default_providers', [
        'chat' => ['provider_id' => 'echoai', 'model_id' => 'test'],
      ])
      ->save();

    $this->generator = $this->container->get(AiSummaryGenerator::class);
  }

  /**
   * Builds an unsaved test node carrying the given title.
   */
  private function createNode(string $title): Node {
    $node = Node::create([
      'type' => 'test_node_bundle',
      'title' => $title,
    ]);
    $node->in_preview = TRUE;
    return $node;
  }

  /**
   * A configured provider yields an HTML bullet-list summary of the content.
   */
  public function testGeneratesSummary(): void {
    $title = 'Generator kernel title ' . $this->randomMachineName();
    $node = $this->createNode($title);

    $summary = $this->generator->generate($node, $node->language()->getId());

    $this->assertNotNull($summary);
    // Output is a bullet list built from the provider reply.
    $this->assertStringStartsWith('<ul><li>', $summary);
    $this->assertStringEndsWith('</li></ul>', $summary);
    // The echoai provider echoes the prompt back, so the node content has
    // travelled the full chain (prompt build → chat call → bullet conversion)
    // into the summary.
    $this->assertStringContainsString($title, $summary);
  }

  /**
   * A missing prompt entity makes generation fail gracefully (NULL).
   */
  public function testReturnsNullWhenPromptMissing(): void {
    $this->container->get('entity_type.manager')
      ->getStorage('ai_prompt')
      ->load('helfi_content_summary__helfi_content_summary_default')
      ->delete();

    $node = $this->createNode('Title ' . $this->randomMachineName());

    $this->assertNull($this->generator->generate($node, $node->language()->getId()));
  }

  /**
   * An unresolvable provider makes generation fail gracefully (NULL).
   */
  public function testReturnsNullWhenProviderUnavailable(): void {
    $this->config('ai.settings')
      ->set('default_providers', [
        'chat' => ['provider_id' => 'no_such_provider', 'model_id' => 'test'],
      ])
      ->save();

    $node = $this->createNode('Title ' . $this->randomMachineName());

    $this->assertNull($this->generator->generate($node, $node->language()->getId()));
  }

}
