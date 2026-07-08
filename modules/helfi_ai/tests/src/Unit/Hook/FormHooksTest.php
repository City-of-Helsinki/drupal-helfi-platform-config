<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Unit\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\helfi_ai\Hook\FormHooks;
use Drupal\helfi_ai\Service\AiGenerator;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the node form alter gating logic for the title suggestion button.
 *
 * AiGenerator is final and depends on the also-final
 * AiProviderPluginManager, so it cannot be doubled here. These scenarios
 * never call the generator, so an uninitialized instance
 * (ReflectionClass::newInstanceWithoutConstructor()) is enough to satisfy
 * the constructor. Behaviour that does call the generator is covered by
 * Drupal\Tests\helfi_ai\Kernel\FormHooksTest.
 */
#[Group('helfi_ai')]
#[CoversClass(FormHooks::class)]
class FormHooksTest extends UnitTestCase {

  /**
   * Builds an AiGenerator instance that is never invoked in these tests.
   */
  private function unusedGenerator(): AiGenerator {
    return (new \ReflectionClass(AiGenerator::class))->newInstanceWithoutConstructor();
  }

  /**
   * Builds the hooks object under test with mocked dependencies.
   *
   * @param bool $hasPermission
   *   Whether the current user holds the suggestion permission.
   * @param string[] $bundles
   *   The configured seo_title_bundles value.
   *
   * @return \Drupal\helfi_ai\Hook\FormHooks
   *   The configured hooks object.
   */
  private function createHooks(bool $hasPermission, array $bundles): FormHooks {
    $account = $this->prophesize(AccountInterface::class);
    $account->hasPermission('use helfi ai title suggestion')->willReturn($hasPermission);

    $config = $this->prophesize(ImmutableConfig::class);
    $config->get('seo_title_bundles')->willReturn($bundles);
    $configFactory = $this->prophesize(ConfigFactoryInterface::class);
    $configFactory->get('helfi_ai.settings')->willReturn($config->reveal());

    return new FormHooks(
      $account->reveal(),
      $configFactory->reveal(),
      $this->unusedGenerator(),
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
    $hooks = $this->createHooks(TRUE, ['page']);
    $hooks->nodeFormAlter($form, $this->nodeFormState('page'), 'node_page_form');

    $this->assertContains('helfi-ai-title', $form['title']['#attributes']['class']);
    $this->assertArrayHasKey('helfi_ai_suggest', $form['title']);
    $button = $form['title']['helfi_ai_suggest']['button'];
    $this->assertSame('helfi_ai_suggest_title', $button['#name']);
    $callback = $button['#ajax']['callback'];
    $this->assertSame($hooks, $callback[0]);
    $this->assertSame('buildSuggestionResponse', $callback[1]);
    $this->assertContains('helfi_ai/title_suggest', $button['#attached']['library']);
  }

  /**
   * No button is added for a content type outside the configured bundles.
   */
  public function testNoButtonForUnconfiguredBundle(): void {
    $form = $this->formWithTitle();
    $this->createHooks(TRUE, ['page'])->nodeFormAlter($form, $this->nodeFormState('news_item'), 'node_news_item_form');

    $this->assertArrayNotHasKey('helfi_ai_suggest', $form['title']);
  }

  /**
   * No button is added when the user lacks the suggestion permission.
   */
  public function testNoButtonWithoutPermission(): void {
    $form = $this->formWithTitle();
    $this->createHooks(FALSE, ['page'])->nodeFormAlter($form, $this->nodeFormState('page'), 'node_page_form');

    $this->assertArrayNotHasKey('helfi_ai_suggest', $form['title']);
  }

  /**
   * No button is added when the form has no title widget.
   */
  public function testNoButtonWhenTitleWidgetMissing(): void {
    $form = [];
    $this->createHooks(TRUE, ['page'])->nodeFormAlter($form, $this->nodeFormState('page'), 'node_page_form');

    $this->assertArrayNotHasKey('title', $form);
  }

  /**
   * No button is added when the form is not a content entity form.
   */
  public function testNoButtonForNonContentEntityForm(): void {
    $formState = $this->prophesize(FormStateInterface::class);
    $formState->getFormObject()->willReturn($this->prophesize(FormInterface::class)->reveal());

    $form = $this->formWithTitle();
    $this->createHooks(TRUE, ['page'])->nodeFormAlter($form, $formState->reveal(), 'some_other_form');

    $this->assertArrayNotHasKey('helfi_ai_suggest', $form['title']);
  }

}
