<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Unit\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\helfi_ai\Form\TitleSuggestionFormAlter;
use Drupal\helfi_ai\Service\AiGenerator;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\Argument;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tests the title suggestion form alter and its AJAX callback.
 */
#[Group('helfi_ai')]
#[CoversClass(TitleSuggestionFormAlter::class)]
class TitleSuggestionFormAlterTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    parent::tearDown();
    // Reset the container so it does not leak into other tests.
    \Drupal::setContainer($this->createMock(ContainerInterface::class));
  }

  /**
   * Builds the service under test with mocked dependencies.
   *
   * @param bool $hasPermission
   *   Whether the current user holds the suggestion permission.
   * @param string[] $bundles
   *   The configured seo_title_bundles value.
   * @param \Drupal\helfi_ai\Service\AiGenerator|null $suggester
   *   The suggester to inject, or NULL for an unused stub.
   *
   * @return \Drupal\helfi_ai\Form\TitleSuggestionFormAlter
   *   The configured service.
   */
  private function createAlter(bool $hasPermission, array $bundles, ?AiGenerator $suggester = NULL): TitleSuggestionFormAlter {
    $account = $this->prophesize(AccountInterface::class);
    $account->hasPermission('use helfi ai title suggestion')->willReturn($hasPermission);

    $config = $this->prophesize(ImmutableConfig::class);
    $config->get('seo_title_bundles')->willReturn($bundles);
    $configFactory = $this->prophesize(ConfigFactoryInterface::class);
    $configFactory->get('helfi_ai.settings')->willReturn($config->reveal());

    return new TitleSuggestionFormAlter(
      $account->reveal(),
      $configFactory->reveal(),
      $suggester ?? $this->prophesize(AiGenerator::class)->reveal(),
      $this->getStringTranslationStub(),
    );
  }

  /**
   * Builds a node edit form state whose entity has the given bundle.
   */
  private function nodeFormState(string $bundle): FormStateInterface {
    $entity = $this->prophesize(NodeInterface::class);
    $entity->bundle()->willReturn($bundle);
    $formObject = $this->prophesize(ContentEntityFormInterface::class);
    $formObject->getEntity()->willReturn($entity->reveal());
    $formState = $this->prophesize(FormStateInterface::class);
    $formState->getFormObject()->willReturn($formObject->reveal());
    return $formState->reveal();
  }

  /**
   * A minimal node form structure carrying the standard title widget.
   *
   * @return array<string, mixed>
   *   The form structure.
   */
  private function formWithTitle(): array {
    return [
      'title' => [
        'widget' => [
          0 => ['value' => ['#type' => 'textfield', '#weight' => 5]],
        ],
      ],
    ];
  }

  /**
   * The button is added for a configured bundle when the user has permission.
   */
  public function testButtonAddedForConfiguredBundleWithPermission(): void {
    $form = $this->formWithTitle();
    $this->createAlter(TRUE, ['page'])->alter($form, $this->nodeFormState('page'));

    $this->assertContains('helfi-ai-title', $form['title']['#attributes']['class']);
    $this->assertArrayHasKey('helfi_ai_suggest', $form['title']);
    $button = $form['title']['helfi_ai_suggest']['button'];
    $this->assertSame('helfi_ai_suggest_title', $button['#name']);
    $callback = $button['#ajax']['callback'];
    $this->assertInstanceOf(TitleSuggestionFormAlter::class, $callback[0]);
    $this->assertSame('buildSuggestionResponse', $callback[1]);
    $this->assertContains('helfi_ai/title_suggest', $button['#attached']['library']);
  }

  /**
   * No button is added for a content type outside the configured bundles.
   */
  public function testNoButtonForUnconfiguredBundle(): void {
    $form = $this->formWithTitle();
    $this->createAlter(TRUE, ['page'])->alter($form, $this->nodeFormState('news_item'));

    $this->assertArrayNotHasKey('helfi_ai_suggest', $form['title']);
  }

  /**
   * No button is added when the user lacks the suggestion permission.
   */
  public function testNoButtonWithoutPermission(): void {
    $form = $this->formWithTitle();
    $this->createAlter(FALSE, ['page'])->alter($form, $this->nodeFormState('page'));

    $this->assertArrayNotHasKey('helfi_ai_suggest', $form['title']);
  }

  /**
   * No button is added when the form has no title widget.
   */
  public function testNoButtonWhenTitleWidgetMissing(): void {
    $form = [];
    $this->createAlter(TRUE, ['page'])->alter($form, $this->nodeFormState('page'));

    $this->assertArrayNotHasKey('title', $form);
  }

  /**
   * No button is added when the form is not a content entity form.
   */
  public function testNoButtonForNonContentEntityForm(): void {
    $formState = $this->prophesize(FormStateInterface::class);
    $formState->getFormObject()->willReturn($this->prophesize(FormInterface::class)->reveal());

    $form = $this->formWithTitle();
    $this->createAlter(TRUE, ['page'])->alter($form, $formState->reveal());

    $this->assertArrayNotHasKey('helfi_ai_suggest', $form['title']);
  }

  /**
   * Returns a suggester stub that yields the given candidates.
   *
   * @param string[] $suggestions
   *   The candidates to return.
   *
   * @return \Drupal\helfi_ai\Service\AiGenerator
   *   The stub.
   */
  private function suggesterReturning(array $suggestions): AiGenerator {
    $suggester = $this->prophesize(AiGenerator::class);
    $suggester->suggestTitles(Argument::any())->willReturn($suggestions);
    return $suggester->reveal();
  }

  /**
   * Installs a container with a renderer that captures the rendered body.
   *
   * @param array<string, mixed> $captured
   *   Receives the render array passed to the renderer.
   */
  private function setRenderer(array &$captured): void {
    $renderer = $this->createMock(RendererInterface::class);
    $renderer->method('renderRoot')->willReturnCallback(
      function (&$elements) use (&$captured): string {
        $captured = $elements;
        $elements['#attached'] = $elements['#attached'] ?? [];
        return '<div>rendered</div>';
      }
    );
    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')->willReturnCallback(
      fn(string $id): object => match ($id) {
        'renderer' => $renderer,
        default => throw new \RuntimeException('Unexpected service: ' . $id),
      }
    );
    \Drupal::setContainer($container);
  }

  /**
   * A form state whose form object builds a content entity.
   */
  private function contentEntityFormState(): FormStateInterface {
    $language = $this->prophesize(LanguageInterface::class);
    $language->getId()->willReturn('fi');
    $entity = $this->prophesize(ContentEntityInterface::class);
    $entity->language()->willReturn($language->reveal());
    $formObject = $this->prophesize(ContentEntityFormInterface::class);
    $formObject->buildEntity(Argument::cetera())->willReturn($entity->reveal());
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
   * Suggestions open a modal with one radio option each and action buttons.
   */
  public function testBuildSuggestionResponseOpensModalWithSuggestions(): void {
    $captured = [];
    $this->setRenderer($captured);
    $alter = $this->createAlter(TRUE, ['page'], $this->suggesterReturning(['Title A', 'Title B']));

    $form = [];
    $response = $alter->buildSuggestionResponse($form, $this->contentEntityFormState());

    $this->assertInstanceOf(AjaxResponse::class, $response);
    $commands = $response->getCommands();
    $this->assertCount(1, $commands);
    $this->assertSame('openDialog', $commands[0]['command']);
    $this->assertSame('#drupal-modal', $commands[0]['selector']);
    $this->assertSame('helfi-ai-dialog', $commands[0]['dialogOptions']['classes']['ui-dialog']);
    $this->assertSame('helfi_ai_title_suggestions', $captured['#theme']);
    $this->assertSame(['Title A', 'Title B'], $captured['#suggestions']);
  }

  /**
   * An empty suggestion list opens a modal with an error message instead.
   */
  public function testBuildSuggestionResponseShowsErrorWhenNoSuggestions(): void {
    $captured = [];
    $this->setRenderer($captured);
    $alter = $this->createAlter(TRUE, ['page'], $this->suggesterReturning([]));

    $form = [];
    $response = $alter->buildSuggestionResponse($form, $this->contentEntityFormState());

    $this->assertSame('openDialog', $response->getCommands()[0]['command']);
    $this->assertSame('helfi_ai_message', $captured['#theme']);
    $this->assertArrayNotHasKey('#suggestions', $captured);
  }

  /**
   * A form whose entity cannot be built opens a modal with an error message.
   */
  public function testBuildSuggestionResponseShowsErrorWhenEntityCannotBeBuilt(): void {
    $captured = [];
    $this->setRenderer($captured);
    $alter = $this->createAlter(TRUE, ['page'], $this->suggesterReturning(['ignored']));

    $form = [];
    $response = $alter->buildSuggestionResponse($form, $this->nonContentEntityFormState());

    $this->assertSame('openDialog', $response->getCommands()[0]['command']);
    $this->assertSame('helfi_ai_message', $captured['#theme']);
  }

}
