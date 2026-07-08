<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Kernel;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\helfi_ai\Hook\FormHooks;
use Drupal\helfi_ai\Service\AiGenerator;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Prophecy\Argument;

/**
 * Tests the suggest-title AJAX callback through the real AI provider stack.
 */
#[Group('helfi_ai')]
#[RunTestsInSeparateProcesses]
class FormHooksTest extends EntityKernelTestBase {

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
   * The hooks object under test.
   */
  private FormHooks $hooks;

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

    $this->hooks = new FormHooks(
      $this->prophesize(AccountInterface::class)->reveal(),
      $this->container->get(ConfigFactoryInterface::class),
      $this->container->get(AiGenerator::class),
    );
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
   * A form state whose form object builds the given content entity.
   */
  private function formStateFor(Node $node): FormStateInterface {
    $formObject = $this->prophesize(ContentEntityFormInterface::class);
    $formObject->buildEntity(Argument::cetera())->willReturn($node);
    $formState = $this->prophesize(FormStateInterface::class);
    $formState->getFormObject()->willReturn($formObject->reveal());
    return $formState->reveal();
  }

  /**
   * A form state whose form object is not a content entity form.
   */
  private function nonContentEntityFormState(): FormStateInterface {
    $formState = $this->prophesize(FormStateInterface::class);
    $formState->getFormObject()->willReturn($this->prophesize(FormInterface::class)->reveal());
    return $formState->reveal();
  }

  /**
   * Extracts the render array captured by an openDialog AJAX command.
   *
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The response to inspect.
   *
   * @return array<string, mixed>
   *   The command's rendered attributes.
   */
  private function dialogCommand(AjaxResponse $response): array {
    $commands = $response->getCommands();
    return $commands[0];
  }

  /**
   * Suggestions open a modal with the theme carrying the generated titles.
   */
  public function testBuildSuggestionResponseOpensModalWithSuggestions(): void {
    $node = $this->createNode('Kernel form hooks title ' . $this->randomMachineName());
    $form = [];

    $response = $this->hooks->buildSuggestionResponse($form, $this->formStateFor($node));

    $command = $this->dialogCommand($response);
    $this->assertSame('openDialog', $command['command']);
    $this->assertSame('#drupal-modal', $command['selector']);
    $this->assertSame('helfi-ai-dialog', $command['dialogOptions']['classes']['ui-dialog']);
    $this->assertStringContainsString('helfi-ai-suggestions', (string) $command['data']);
  }

  /**
   * A form whose entity cannot be built opens a modal with an error message.
   */
  public function testBuildSuggestionResponseShowsErrorWhenEntityCannotBeBuilt(): void {
    $form = [];

    $response = $this->hooks->buildSuggestionResponse($form, $this->nonContentEntityFormState());

    $command = $this->dialogCommand($response);
    $this->assertSame('openDialog', $command['command']);
    $this->assertStringContainsString('Could not read the page content.', (string) $command['data']);
  }

  /**
   * An unresolvable provider makes suggestion fail, showing an error message.
   */
  public function testBuildSuggestionResponseShowsErrorWhenProviderUnavailable(): void {
    $this->config('ai.settings')
      ->set('default_providers', [
        'chat' => ['provider_id' => 'no_such_provider', 'model_id' => 'test'],
      ])
      ->save();

    $node = $this->createNode('Kernel form hooks title ' . $this->randomMachineName());
    $form = [];

    $response = $this->hooks->buildSuggestionResponse($form, $this->formStateFor($node));

    $command = $this->dialogCommand($response);
    $this->assertSame('openDialog', $command['command']);
    $this->assertStringContainsString('Could not generate title suggestions.', (string) $command['data']);
  }

}
