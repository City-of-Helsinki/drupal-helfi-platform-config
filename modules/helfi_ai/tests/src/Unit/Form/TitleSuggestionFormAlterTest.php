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
use Drupal\helfi_ai\Service\AiTitleSuggester;
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
   *
   * @return \Drupal\helfi_ai\Form\TitleSuggestionFormAlter
   *   The configured service.
   */
  private function createAlter(bool $hasPermission, array $bundles): TitleSuggestionFormAlter {
    $account = $this->prophesize(AccountInterface::class);
    $account->hasPermission('use helfi ai title suggestion')->willReturn($hasPermission);

    $config = $this->prophesize(ImmutableConfig::class);
    $config->get('seo_title_bundles')->willReturn($bundles);
    $configFactory = $this->prophesize(ConfigFactoryInterface::class);
    $configFactory->get('helfi_ai.settings')->willReturn($config->reveal());

    return new TitleSuggestionFormAlter(
      $account->reveal(),
      $configFactory->reveal(),
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

    $this->assertArrayHasKey('helfi_ai_suggest', $form['title']);
    $button = $form['title']['helfi_ai_suggest']['button'];
    $this->assertSame('helfi_ai_suggest_title', $button['#name']);
    $this->assertSame([TitleSuggestionFormAlter::class, 'ajaxCallback'], $button['#ajax']['callback']);
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
   * Wires \Drupal and a form state for the static ajax callback.
   *
   * @param string[] $suggestions
   *   The candidates the mocked suggester returns.
   * @param bool $contentForm
   *   Whether the form object is a content entity form (so an entity builds).
   * @param array<string, mixed> $captured
   *   Receives the render array passed to the renderer (by reference).
   *
   * @return \Drupal\Core\Form\FormStateInterface
   *   The form state to pass to the callback.
   */
  private function setUpAjax(array $suggestions, bool $contentForm, array &$captured): FormStateInterface {
    $suggester = $this->prophesize(AiTitleSuggester::class);
    $suggester->suggest(Argument::any(), Argument::any())->willReturn($suggestions);

    $renderer = $this->createMock(RendererInterface::class);
    $renderer->method('renderRoot')->willReturnCallback(
      function (&$elements) use (&$captured): string {
        $captured = $elements;
        // The real renderer populates #attached; the dialog command reads it.
        $elements['#attached'] = $elements['#attached'] ?? [];
        return '<div>rendered</div>';
      }
    );

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')->willReturnCallback(
      function (string $id) use ($suggester, $renderer): object {
        return match ($id) {
          AiTitleSuggester::class => $suggester->reveal(),
          'renderer' => $renderer,
          'string_translation' => $this->getStringTranslationStub(),
          default => throw new \RuntimeException('Unexpected service: ' . $id),
        };
      }
    );
    \Drupal::setContainer($container);

    $formState = $this->prophesize(FormStateInterface::class);
    if ($contentForm) {
      $language = $this->prophesize(LanguageInterface::class);
      $language->getId()->willReturn('fi');
      $entity = $this->prophesize(ContentEntityInterface::class);
      $entity->language()->willReturn($language->reveal());
      $formObject = $this->prophesize(ContentEntityFormInterface::class);
      $formObject->buildEntity(Argument::cetera())->willReturn($entity->reveal());
      $formState->getFormObject()->willReturn($formObject->reveal());
    }
    else {
      $formState->getFormObject()->willReturn($this->prophesize(FormInterface::class)->reveal());
    }

    return $formState->reveal();
  }

  /**
   * Suggestions open a modal with one radio option each and action buttons.
   */
  public function testAjaxCallbackOpensModalWithSuggestions(): void {
    $captured = [];
    $formState = $this->setUpAjax(['Title A', 'Title B'], TRUE, $captured);

    $form = [];
    $response = TitleSuggestionFormAlter::ajaxCallback($form, $formState);

    $this->assertInstanceOf(AjaxResponse::class, $response);
    $commands = $response->getCommands();
    $this->assertCount(1, $commands);
    $this->assertSame('openDialog', $commands[0]['command']);
    $this->assertSame('#drupal-modal', $commands[0]['selector']);
    $this->assertSame('helfi-ai-title-dialog', $commands[0]['dialogOptions']['classes']['ui-dialog']);
    // The rendered body is the radio option box, one option per suggestion,
    // plus the action buttons.
    $this->assertArrayHasKey('options', $captured);
    $this->assertArrayHasKey('actions', $captured);
    $this->assertArrayHasKey('option_0', $captured['options']);
    $this->assertArrayHasKey('option_1', $captured['options']);
  }

  /**
   * An empty suggestion list opens a modal with an error message instead.
   */
  public function testAjaxCallbackShowsErrorWhenNoSuggestions(): void {
    $captured = [];
    $formState = $this->setUpAjax([], TRUE, $captured);

    $form = [];
    $response = TitleSuggestionFormAlter::ajaxCallback($form, $formState);

    $this->assertSame('openDialog', $response->getCommands()[0]['command']);
    // The body is a plain message paragraph, not the option box.
    $this->assertSame('p', $captured['#tag']);
    $this->assertArrayNotHasKey('options', $captured);
  }

  /**
   * A form whose entity cannot be built opens a modal with an error message.
   */
  public function testAjaxCallbackShowsErrorWhenEntityCannotBeBuilt(): void {
    $captured = [];
    $formState = $this->setUpAjax(['ignored'], FALSE, $captured);

    $form = [];
    $response = TitleSuggestionFormAlter::ajaxCallback($form, $formState);

    $this->assertSame('openDialog', $response->getCommands()[0]['command']);
    $this->assertSame('p', $captured['#tag']);
  }

}
