<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Unit\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\helfi_ai\Plugin\Field\FieldWidget\AiSummaryWidget;
use Drupal\helfi_api_base\Environment\ActiveProjectRoles;
use Drupal\helfi_api_base\Environment\EnvironmentResolverException;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_api_base\Environment\ProjectMetadata;
use Drupal\helfi_api_base\Environment\ProjectRoleEnum;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\Argument;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tests the AI summary field widget.
 */
#[Group('helfi_ai')]
class AiSummaryWidgetTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    parent::tearDown();
    // Reset the container so it doesn't leak into other tests.
    $container = $this->createMock(ContainerInterface::class);
    \Drupal::setContainer($container);
  }

  /**
   * Creates a widget instance for the given field name.
   */
  private function createWidget(bool $access = TRUE, ?string $environment = 'test', bool $core = TRUE): AiSummaryWidget {
    $fieldDef = $this->prophesize(FieldDefinitionInterface::class);
    $fieldDef->getName()->willReturn('ai_summary');

    $accountProxy = $this->prophesize(AccountProxyInterface::class);
    $accountProxy->hasPermission('use helfi ai summary')->willReturn($access);

    $environmentResolver = $this->prophesize(EnvironmentResolverInterface::class);
    if ($environment === NULL) {
      $environmentResolver->getActiveEnvironmentName()->willThrow(new EnvironmentResolverException());
    }
    else {
      $environmentResolver->getActiveEnvironmentName()->willReturn($environment);
    }
    $environmentResolver->getActiveProject()->willReturn(new Project(
      'test',
      new ProjectMetadata('https://github.com/City-of-Helsinki/test'),
      roles: $core ? [ProjectRoleEnum::Core] : [],
    ));

    $widget = new AiSummaryWidget('ai_summary', [], $fieldDef->reveal(), [], [], $accountProxy->reveal(), new ActiveProjectRoles($environmentResolver->reveal()), $environmentResolver->reveal());
    $widget->setStringTranslation($this->getStringTranslationStub());
    return $widget;
  }

  /**
   * Builds a FieldItemListInterface prophecy with a single item value.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface>
   *   The field item list prophecy double.
   */
  private function makeItems(
    string $savedValue,
    string $entityTypeId = 'node',
    string $bundle = 'page',
  ): FieldItemListInterface {
    $fieldDef = $this->prophesize(FieldDefinitionInterface::class);
    $fieldDef->getName()->willReturn('ai_summary');

    $entity = $this->prophesize(EntityInterface::class);
    $entity->getEntityTypeId()->willReturn($entityTypeId);
    $entity->bundle()->willReturn($bundle);

    $item = new \stdClass();
    $item->value = $savedValue;

    $items = $this->prophesize(FieldItemListInterface::class);
    $items->getFieldDefinition()->willReturn($fieldDef->reveal());
    $items->getEntity()->willReturn($entity->reveal());
    $items->offsetExists(Argument::any())->willReturn(TRUE);
    $items->offsetGet(Argument::any())->willReturn($item);
    return $items->reveal();
  }

  /**
   * Test that the widget applies to the ai_summary field.
   */
  public function testIsApplicableReturnsTrueForAiSummaryField(): void {
    $fieldDef = $this->prophesize(FieldDefinitionInterface::class);
    $fieldDef->getName()->willReturn('ai_summary');
    $this->assertTrue(AiSummaryWidget::isApplicable($fieldDef->reveal()));
  }

  /**
   * Test that the widget does not apply to other fields.
   */
  public function testIsApplicableReturnsFalseForOtherFields(): void {
    $fieldDef = $this->prophesize(FieldDefinitionInterface::class);
    $fieldDef->getName()->willReturn('field_body');
    $this->assertFalse(AiSummaryWidget::isApplicable($fieldDef->reveal()));
  }

  /**
   * Test that an empty field renders only the generate button.
   */
  public function testFormElementEmptyValueBuildsGenerateButton(): void {
    $widget = $this->createWidget();

    $form = [];
    $formState = new FormState();
    $result = $widget->formElement($this->makeItems(''), 0, [], $form, $formState);

    $wrapper = $result['ajax_wrapper'];
    $this->assertSame('ai-summary-ai-summary-0', $wrapper['#attributes']['id']);
    // Empty field: the editor container is hidden, only the button shows.
    $this->assertArrayHasKey('summary', $wrapper);
    $this->assertContains('hidden', $wrapper['summary']['#attributes']['class']);
    $this->assertArrayHasKey('field_label', $wrapper);
    $this->assertArrayHasKey('value', $wrapper['summary']);
    $this->assertSame('text_format', $wrapper['summary']['value']['#type']);
    $this->assertSame('', $wrapper['summary']['value']['#default_value']);
    $this->assertSame('minimal', $wrapper['summary']['value']['#format']);
    $this->assertArrayHasKey('generate', $wrapper);
    $this->assertSame('ai_summary_generate_ai_summary_0', $wrapper['generate']['#name']);
    $this->assertSame('Generate AI summary', (string) $wrapper['generate']['#value']);
    $this->assertContains('helfi_ai/ai_summary_confirm', $wrapper['generate']['#attached']['library']);
    $this->assertArrayNotHasKey('data-ai-summary-confirm', $wrapper['generate']['#attributes'] ?? []);
    $this->assertArrayHasKey('description', $wrapper);
    $this->assertArrayNotHasKey('error', $wrapper);
  }

  /**
   * Test widget visibility per content type.
   */
  #[DataProvider('bundleProvider')]
  public function testFormElementVisibilityPerBundle(string $entityTypeId, string $bundle, bool $expected): void {
    $widget = $this->createWidget();

    $form = [];
    $result = $widget->formElement($this->makeItems('', $entityTypeId, $bundle), 0, [], $form, new FormState());

    $this->assertSame($expected, isset($result['ajax_wrapper']), 'Widget visibility matches the content type.');
  }

  /**
   * Data provider for content type visibility.
   *
   * @return array<string, array{0: string, 1: string, 2: bool}>
   *   The entity type, bundle and expected visibility.
   */
  public static function bundleProvider(): array {
    return [
      'news item' => ['node', 'news_item', TRUE],
      'news article' => ['node', 'news_article', TRUE],
      'basic page' => ['node', 'page', TRUE],
      'landing page' => ['node', 'landing_page', FALSE],
      'tpr service' => ['tpr_service', 'tpr_service', FALSE],
    ];
  }

  /**
   * Test that the widget is hidden without permission.
   */
  public function testFormElementHiddenWithoutPermission(): void {
    $widget = $this->createWidget(FALSE);

    $form = [];
    $result = $widget->formElement($this->makeItems(''), 0, [], $form, new FormState());

    $this->assertFalse($result['#access']);
  }

  /**
   * Test that the widget is hidden on non-core sites.
   */
  public function testFormElementHiddenOnNonCoreSite(): void {
    $widget = $this->createWidget(TRUE, 'test', FALSE);

    $form = [];
    $result = $widget->formElement($this->makeItems(''), 0, [], $form, new FormState());

    $this->assertFalse($result['#access']);
  }

  /**
   * Test widget visibility per environment.
   *
   * @todo UHF-13833 Remove when AI summary is enabled in production.
   */
  #[DataProvider('environmentProvider')]
  public function testFormElementVisibilityPerEnvironment(?string $environment, bool $core, bool $expected): void {
    $widget = $this->createWidget(TRUE, $environment, $core);

    $form = [];
    $result = $widget->formElement($this->makeItems(''), 0, [], $form, new FormState());

    $this->assertSame($expected, isset($result['ajax_wrapper']), 'Widget visibility matches the environment.');
  }

  /**
   * Data provider for environment visibility.
   *
   * @return array<string, array{0: ?string, 1: bool, 2: bool}>
   *   The environment, core role and expected visibility.
   *
   * @todo UHF-13833 Remove when AI summary is enabled in production.
   */
  public static function environmentProvider(): array {
    return [
      'core local' => ['local', TRUE, TRUE],
      'core dev' => ['dev', TRUE, FALSE],
      'core test' => ['test', TRUE, TRUE],
      'core stage' => ['stage', TRUE, FALSE],
      'core prod' => ['prod', TRUE, FALSE],
      'non-core test' => ['test', FALSE, FALSE],
      'unknown environment' => [NULL, TRUE, FALSE],
    ];
  }

  /**
   * Test that a saved value shows the editor and regenerate button.
   */
  public function testFormElementWithSavedValueShowsRegenerateAndDefault(): void {
    $widget = $this->createWidget();

    $form = [];
    $formState = new FormState();
    $result = $widget->formElement($this->makeItems('<ul><li>Saved</li></ul>'), 0, [], $form, $formState);

    $wrapper = $result['ajax_wrapper'];
    $this->assertNotContains('hidden', $wrapper['summary']['#attributes']['class']);
    $this->assertSame('<ul><li>Saved</li></ul>', $wrapper['summary']['value']['#default_value']);
    $this->assertSame('Regenerate AI summary', (string) $wrapper['generate']['#value']);
    $this->assertArrayHasKey('data-ai-summary-confirm', $wrapper['generate']['#attributes']);
    $this->assertContains('helfi_ai/ai_summary_confirm', $wrapper['generate']['#attached']['library']);
  }

  /**
   * Test that the wrapper id includes the field delta.
   */
  public function testFormElementWrapperIdIncludesDelta(): void {
    $widget = $this->createWidget();

    $form = [];
    $formState = new FormState();
    $result = $widget->formElement($this->makeItems(''), 2, [], $form, $formState);

    $this->assertSame('ai-summary-ai-summary-2', $result['ajax_wrapper']['#attributes']['id']);
  }

  /**
   * Test that the generate button is a plain ajax button.
   */
  public function testFormElementButtonIsPlainAjaxButtonWithoutSubmit(): void {
    $widget = $this->createWidget();

    $form = [];
    $formState = new FormState();
    $result = $widget->formElement($this->makeItems(''), 0, [], $form, $formState);

    $button = $result['ajax_wrapper']['generate'];
    $this->assertSame('button', $button['#type']);
    $this->assertArrayNotHasKey('#submit', $button);
    $this->assertArrayNotHasKey('#executes_submit_callback', $button);
    $this->assertArrayNotHasKey('#limit_validation_errors', $button);
    $this->assertSame([AiSummaryWidget::class, 'ajaxCallback'], $button['#ajax']['callback']);
    $this->assertSame('ai-summary-ai-summary-0', $button['#ajax']['wrapper']);
    $this->assertSame('click', $button['#ajax']['event']);
  }

  /**
   * Test that the edited value is read from the nested form path.
   */
  public function testExtractFormValuesSetsValueFromNestedPath(): void {
    $widget = $this->createWidget();

    $formState = new FormState();
    $formState->setValue(['ai_summary', 0, 'ajax_wrapper', 'summary', 'value'], [
      'value' => '<ul><li>Edited</li></ul>',
      'format' => 'minimal',
    ]);

    $items = $this->prophesize(FieldItemListInterface::class);
    $items->setValue([['value' => '<ul><li>Edited</li></ul>', 'format' => 'minimal']])->shouldBeCalled();

    $widget->extractFormValues($items->reveal(), [], $formState);
  }

  /**
   * Test that nothing is set when no value is submitted.
   */
  public function testExtractFormValuesSkipsWhenNoValue(): void {
    $widget = $this->createWidget();

    $formState = new FormState();

    $items = $this->prophesize(FieldItemListInterface::class);
    $items->setValue(Argument::any())->shouldNotBeCalled();

    $widget->extractFormValues($items->reveal(), [], $formState);
  }

}
