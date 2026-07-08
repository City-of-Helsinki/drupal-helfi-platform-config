<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Kernel;

use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\helfi_ai\Service\AiGenerator;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests AI title suggestion and summary generation through the AI provider.
 */
#[Group('helfi_ai')]
#[RunTestsInSeparateProcesses]
class AiGeneratorTest extends EntityKernelTestBase {

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
   * The generator under test.
   */
  private AiGenerator $generator;

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

    $this->generator = $this->container->get(AiGenerator::class);
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
   * A configured provider yields a non-empty, capped list of title candidates.
   */
  public function testSuggestTitlesReturnsSuggestions(): void {
    $node = $this->createNode('Generator kernel title ' . $this->randomMachineName());

    $suggestions = $this->generator->suggestTitles($node);

    $this->assertNotEmpty($suggestions);
    $this->assertLessThanOrEqual(3, count($suggestions));
    foreach ($suggestions as $suggestion) {
      $this->assertIsString($suggestion);
      $this->assertNotSame('', trim($suggestion));
    }
  }

  /**
   * Content larger than the byte cap is skipped.
   */
  public function testSuggestTitlesReturnsEmptyWhenContentTooLarge(): void {
    $node = $this->createNode(str_repeat('A', 300 * 1024));
    $this->assertSame([], $this->generator->suggestTitles($node));
  }

  /**
   * A missing prompt entity makes suggestion fail gracefully.
   */
  public function testSuggestTitlesReturnsEmptyWhenPromptMissing(): void {
    $this->container->get('entity_type.manager')
      ->getStorage('ai_prompt')
      ->load('helfi_seo_title__helfi_seo_title_default')
      ->delete();

    $node = $this->createNode('Title ' . $this->randomMachineName());

    $this->assertSame([], $this->generator->suggestTitles($node));
  }

  /**
   * An unresolvable provider makes suggestion fail gracefully.
   */
  public function testSuggestTitlesReturnsEmptyWhenProviderUnavailable(): void {
    $this->config('ai.settings')
      ->set('default_providers', [
        'chat' => ['provider_id' => 'no_such_provider', 'model_id' => 'test'],
      ])
      ->save();

    $node = $this->createNode('Title ' . $this->randomMachineName());

    $this->assertSame([], $this->generator->suggestTitles($node));
  }

  /**
   * A configured provider yields an HTML bullet-list summary of the content.
   */
  public function testGenerateSummaryReturnsSummary(): void {
    $title = 'Generator kernel title ' . $this->randomMachineName();
    $node = $this->createNode($title);

    $summary = $this->generator->generateSummary($node);

    $this->assertNotNull($summary);
    $this->assertStringStartsWith('<ul><li>', $summary);
    $this->assertStringEndsWith('</li></ul>', $summary);
    $this->assertStringContainsString($title, $summary);
  }

  /**
   * A missing prompt entity makes generation fail gracefully.
   */
  public function testGenerateSummaryReturnsNullWhenPromptMissing(): void {
    $this->container->get('entity_type.manager')
      ->getStorage('ai_prompt')
      ->load('helfi_content_summary__helfi_content_summary_default')
      ->delete();

    $node = $this->createNode('Title ' . $this->randomMachineName());

    $this->assertNull($this->generator->generateSummary($node));
  }

  /**
   * An unresolvable provider makes generation fail gracefully.
   */
  public function testGenerateSummaryReturnsNullWhenProviderUnavailable(): void {
    $this->config('ai.settings')
      ->set('default_providers', [
        'chat' => ['provider_id' => 'no_such_provider', 'model_id' => 'test'],
      ])
      ->save();

    $node = $this->createNode('Title ' . $this->randomMachineName());

    $this->assertNull($this->generator->generateSummary($node));
  }

}
