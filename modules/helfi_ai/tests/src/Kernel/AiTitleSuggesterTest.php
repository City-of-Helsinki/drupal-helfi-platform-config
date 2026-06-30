<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Kernel;

use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\helfi_ai\Service\AiTitleSuggester;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests AI SEO-title suggestion through the real AI provider stack.
 *
 * Runs the suggester against the AI module's echoai test provider, so the whole
 * chain is exercised for real (provider resolution, prompt building, the chat
 * call, and reply parsing) without any external service or API key. The echoai
 * provider echoes the prompt back, which the suggester splits into candidates.
 */
#[Group('helfi_ai')]
#[RunTestsInSeparateProcesses]
class AiTitleSuggesterTest extends EntityKernelTestBase {

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
   * The title suggester under test.
   */
  private AiTitleSuggester $suggester;

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

    $this->suggester = $this->container->get(AiTitleSuggester::class);
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
  public function testReturnsSuggestions(): void {
    $node = $this->createNode('Suggester kernel title ' . $this->randomMachineName());

    $suggestions = $this->suggester->suggest($node, $node->language()->getId());

    // The echoai provider echoes the (multi-line) prompt, so the suggester
    // parses several lines and caps them at three non-empty candidates.
    $this->assertNotEmpty($suggestions);
    $this->assertLessThanOrEqual(3, count($suggestions));
    foreach ($suggestions as $suggestion) {
      $this->assertIsString($suggestion);
      $this->assertNotSame('', trim($suggestion));
    }
  }

  /**
   * A missing prompt entity makes suggestion fail gracefully (empty array).
   */
  public function testReturnsEmptyWhenPromptMissing(): void {
    $this->container->get('entity_type.manager')
      ->getStorage('ai_prompt')
      ->load('helfi_seo_title__helfi_seo_title_default')
      ->delete();

    $node = $this->createNode('Title ' . $this->randomMachineName());

    $this->assertSame([], $this->suggester->suggest($node, $node->language()->getId()));
  }

  /**
   * An unresolvable provider makes suggestion fail gracefully (empty array).
   */
  public function testReturnsEmptyWhenProviderUnavailable(): void {
    $this->config('ai.settings')
      ->set('default_providers', [
        'chat' => ['provider_id' => 'no_such_provider', 'model_id' => 'test'],
      ])
      ->save();

    $node = $this->createNode('Title ' . $this->randomMachineName());

    $this->assertSame([], $this->suggester->suggest($node, $node->language()->getId()));
  }

}
