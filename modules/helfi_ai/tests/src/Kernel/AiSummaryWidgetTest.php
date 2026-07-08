<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Kernel;

use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\Core\Form\FormState;
use Drupal\helfi_ai\Plugin\Field\FieldWidget\AiSummaryWidget;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Prophecy\Argument;

/**
 * Tests the AI summary field widget's AJAX callback.
 *
 * AiSummaryWidget::ajaxCallback() fetches AiGenerator via the service
 * locator. AiGenerator is final (and depends on the also-final
 * AiProviderPluginManager), so it cannot be mocked; this exercises the
 * callback with the real service, backed by the echoai test provider.
 */
#[Group('helfi_ai')]
#[RunTestsInSeparateProcesses]
class AiSummaryWidgetTest extends EntityKernelTestBase {

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
  }

  /**
   * Builds a form + form state wired for the AJAX callback.
   *
   * @param string $title
   *   The title of the unsaved node the callback will summarize.
   *
   * @return array{0: array<string, mixed>, 1: \Drupal\Core\Form\FormState}
   *   The form structure and form state.
   */
  private function makeAjaxContext(string $title): array {
    $node = Node::create(['type' => 'test_node_bundle', 'title' => $title]);
    $node->in_preview = TRUE;

    $formObject = $this->prophesize(ContentEntityFormInterface::class);
    $formObject->buildEntity(Argument::cetera())->willReturn($node);

    $wrapperId = 'ai-summary-ai-summary-0';
    $form = [
      'ai_summary' => [
        0 => [
          'ajax_wrapper' => [
            '#type' => 'container',
            '#attributes' => ['id' => $wrapperId],
            'summary' => [
              '#type' => 'container',
              '#attributes' => ['class' => ['hidden']],
              'value' => [
                '#type' => 'text_format',
                'value' => ['#type' => 'textarea', '#value' => ''],
                'format' => ['#type' => 'select'],
              ],
            ],
            'generate' => ['#type' => 'button', '#value' => 'Generate AI summary'],
          ],
        ],
      ],
    ];

    $formState = new FormState();
    $formState->setFormObject($formObject->reveal());
    $formState->setTriggeringElement([
      '#array_parents' => ['ai_summary', 0, 'ajax_wrapper', 'generate'],
    ]);

    return [$form, $formState];
  }

  /**
   * A generated summary is injected into the form and the button relabelled.
   */
  public function testAjaxCallbackInjectsSummaryOnSuccess(): void {
    // Resolve chat operations to the echoai test provider.
    $this->config('ai.settings')
      ->set('default_providers', [
        'chat' => ['provider_id' => 'echoai', 'model_id' => 'test'],
      ])
      ->save();

    $title = 'Widget kernel title ' . $this->randomMachineName();
    [$form, $formState] = $this->makeAjaxContext($title);

    $response = AiSummaryWidget::ajaxCallback($form, $formState);

    $commands = $response->getCommands();
    $this->assertCount(1, $commands);
    $this->assertSame('#ai-summary-ai-summary-0', $commands[0]['selector']);
    $this->assertSame('replaceWith', $commands[0]['method']);
    $rendered = (string) $commands[0]['data'];
    // The summary markup is injected into a <textarea>, so it is HTML-escaped.
    $this->assertStringContainsString('&lt;ul&gt;&lt;li&gt;', $rendered);
    $this->assertStringContainsString($title, $rendered);
    $this->assertStringContainsString('Regenerate AI summary', $rendered);
    $this->assertStringContainsString('data-helfi-ai-summary-confirm', $rendered);
  }

  /**
   * An error is shown when generation returns nothing.
   */
  public function testAjaxCallbackShowsErrorWhenGeneratorReturnsNull(): void {
    // An unresolvable provider makes generation fail gracefully.
    $this->config('ai.settings')
      ->set('default_providers', [
        'chat' => ['provider_id' => 'no_such_provider', 'model_id' => 'test'],
      ])
      ->save();

    [$form, $formState] = $this->makeAjaxContext('Widget kernel title ' . $this->randomMachineName());

    $response = AiSummaryWidget::ajaxCallback($form, $formState);

    $rendered = (string) $response->getCommands()[0]['data'];
    $this->assertStringContainsString('Could not generate a summary.', $rendered);
  }

}
