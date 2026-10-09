<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Kernel;

use Drupal\Core\Access\CsrfRequestHeaderAccessCheck;
use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\helfi_ai\Plugin\Field\FieldWidget\AiSummaryWidget;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\helfi_ai\Traits\TextServicesTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Prophecy\Argument;

/**
 * Tests the AI summary field widget's AJAX callback.
 */
#[Group('helfi_ai')]
#[RunTestsInSeparateProcesses]
class AiSummaryWidgetTest extends EntityKernelTestBase {

  use TextServicesTestTrait;

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
    'helfi_ai',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['helfi_ai']);
    $this->setUpTextServices();

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
   * Generating starts the summary stream with the text of the form state.
   */
  public function testAjaxCallbackStartsSummaryStream(): void {
    $title = 'Widget kernel title ' . $this->randomMachineName();
    [$form, $formState] = $this->makeAjaxContext($title);

    $response = AiSummaryWidget::ajaxCallback($form, $formState);

    // The summary itself is streamed by the browser, not requested here.
    $this->assertNull($this->textServicesResponses->getLastRequest());
    $commands = $response->getCommands();
    $this->assertCount(1, $commands);
    $this->assertSame('helfiAiSummaryStream', $commands[0]['command']);
    $this->assertSame('ai-summary-ai-summary-0', $commands[0]['wrapperId']);
    $this->assertStringContainsString($title, $commands[0]['text']);
    $this->assertSame('en', $commands[0]['langcode']);
    $this->assertStringEndsWith('/helfi-ai/summary', $commands[0]['url']);
    $this->assertTrue($this->container->get('csrf_token')->validate($commands[0]['csrfToken'], CsrfRequestHeaderAccessCheck::TOKEN_KEY));
  }

  /**
   * An error is shown when there is no content to summarize.
   */
  public function testAjaxCallbackShowsErrorWithoutContent(): void {
    [$form, $formState] = $this->makeAjaxContext('Widget kernel title');
    $formState->setFormObject($this->prophesize(FormInterface::class)->reveal());

    $response = AiSummaryWidget::ajaxCallback($form, $formState);

    $this->assertNull($this->textServicesResponses->getLastRequest());
    $rendered = (string) $response->getCommands()[0]['data'];
    $this->assertStringContainsString('Could not complete the AI request. Please try again.', $rendered);
  }

}
