<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Kernel;

use Drupal\ai\Event\PreGenerateResponseEvent;
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
 * Tests that each AI feature runs on the model configured for its tier.
 */
#[Group('helfi_ai')]
#[RunTestsInSeparateProcesses]
class ModelTierTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_platform_config',
    'diff',
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
   * The model id the provider was last asked for.
   */
  private ?string $requestedModel = NULL;

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

    // The site-wide provider, used when no tier resolves.
    $this->config('ai.settings')
      ->set('default_providers', [
        'chat' => ['provider_id' => 'echoai', 'model_id' => 'site-wide-model'],
      ])
      ->save();

    $this->container->get('event_dispatcher')->addListener(
      PreGenerateResponseEvent::EVENT_NAME,
      function (PreGenerateResponseEvent $event): void {
        $this->requestedModel = $event->getModelId();
      },
    );

    $this->generator = $this->container->get(AiGenerator::class);
  }

  /**
   * Writes the tier to provider mapping settings.php would normally provide.
   *
   * @param array<string, string> $tiers
   *   The tier mapping.
   */
  private function setTiers(array $tiers): void {
    $this->config('helfi_ai.settings')->set('model_tiers', $tiers)->save();
  }

  /**
   * All three tiers configured.
   *
   * @return array<string, string>
   *   The tier mapping.
   */
  private function allTiers(): array {
    return [
      'default' => 'echoai__default-model',
      'low' => 'echoai__low-model',
      'high' => 'echoai__high-model',
    ];
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
   * Tone check asks for the high tier.
   */
  public function testToneCheckUsesHighTier(): void {
    $this->setTiers($this->allTiers());

    $this->generator->checkTone('<p>Some content to check.</p>', 'en');

    $this->assertSame('high-model', $this->requestedModel);
  }

  /**
   * Summary generation asks for the low tier.
   */
  public function testSummaryUsesLowTier(): void {
    $this->setTiers($this->allTiers());

    $this->generator->generateSummary($this->createNode('Summary tier node'));

    $this->assertSame('low-model', $this->requestedModel);
  }

  /**
   * Title suggestion asks for the low tier.
   */
  public function testTitleSuggestionUsesLowTier(): void {
    $this->setTiers($this->allTiers());

    $this->generator->suggestTitles($this->createNode('Title tier node'));

    $this->assertSame('low-model', $this->requestedModel);
  }

  /**
   * A tier that is not configured falls back to the default tier.
   */
  public function testUnconfiguredTierFallsBackToDefaultTier(): void {
    $this->setTiers(['default' => 'echoai__default-model']);

    $this->generator->checkTone('<p>Some content to check.</p>', 'en');

    $this->assertSame('default-model', $this->requestedModel);
  }

  /**
   * With no tiers at all the site-wide provider is used.
   *
   * This is the behaviour for environments that configure no tier variables.
   */
  public function testNoTiersFallBackToSiteWideProvider(): void {
    $this->generator->checkTone('<p>Some content to check.</p>', 'en');

    $this->assertSame('site-wide-model', $this->requestedModel);
  }

}
