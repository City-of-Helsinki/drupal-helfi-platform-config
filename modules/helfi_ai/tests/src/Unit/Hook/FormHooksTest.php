<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Unit\Hook;

use Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\helfi_ai\Hook\FormHooks;
use Drupal\helfi_ai\Service\TextServicesGenerator;
use Drupal\helfi_api_base\Environment\ActiveProjectRoles;
use Drupal\helfi_api_base\Environment\EnvironmentResolverException;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_api_base\Environment\ProjectMetadata;
use Drupal\helfi_api_base\Environment\ProjectRoleEnum;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the node form alter gating logic for the title suggestion button.
 */
#[Group('helfi_ai')]
#[CoversClass(FormHooks::class)]
class FormHooksTest extends UnitTestCase {

  /**
   * Builds the hooks object under test with mocked dependencies.
   *
   * @param bool $hasPermission
   *   Whether the current user holds the suggestion permission.
   * @param string[] $bundles
   *   The configured seo_title_bundles value.
   * @param bool $toneCheckEnabled
   *   Whether the AI tone check feature is enabled.
   * @param string|null $environment
   *   The active environment name.
   * @param bool $core
   *   Whether the project has the core role.
   *
   * @return \Drupal\helfi_ai\Hook\FormHooks
   *   The configured hooks object.
   */
  private function createHooks(
    bool $hasPermission,
    array $bundles,
    bool $toneCheckEnabled = TRUE,
    ?string $environment = 'test',
    bool $core = TRUE,
  ): FormHooks {
    $account = $this->prophesize(AccountInterface::class);
    $account->hasPermission('use helfi ai title suggestion')->willReturn($hasPermission);

    $config = $this->prophesize(ImmutableConfig::class);
    $config->get('enable_tone_check')->willReturn($toneCheckEnabled);
    $config->get('seo_title_bundles')->willReturn($bundles);
    $configFactory = $this->prophesize(ConfigFactoryInterface::class);
    $configFactory->get('helfi_ai.settings')->willReturn($config->reveal());

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

    return new FormHooks(
      $account->reveal(),
      $configFactory->reveal(),
      $this->createMock(TextServicesGenerator::class),
      new ActiveProjectRoles($environmentResolver->reveal()),
      $environmentResolver->reveal(),
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

    $this->assertContains('ai-title', $form['title']['#attributes']['class']);
    $this->assertArrayHasKey('helfi_ai_suggest', $form['title']);
    $button = $form['title']['helfi_ai_suggest']['button'];
    $this->assertSame('helfi_ai_suggest_title', $button['#name']);
    $callback = $button['#ajax']['callback'];
    $this->assertSame($hooks, $callback[0]);
    $this->assertSame('buildSuggestionResponse', $callback[1]);
    $this->assertContains('helfi_ai/title_suggest', $button['#attached']['library']);
  }

  /**
   * Test button visibility per environment.
   *
   * @todo UHF-13643 Remove when AI title suggestion is enabled in production.
   */
  #[DataProvider('environmentProvider')]
  public function testButtonVisibilityPerEnvironment(?string $environment, bool $core, bool $expected): void {
    $form = $this->formWithTitle();
    $this->createHooks(TRUE, ['page'], environment: $environment, core: $core)
      ->nodeFormAlter($form, $this->nodeFormState('page'), 'node_page_form');

    $this->assertSame($expected, isset($form['title']['helfi_ai_suggest']), 'Button visibility matches the environment.');
  }

  /**
   * Data provider for environment visibility.
   *
   * @return array<string, array{0: ?string, 1: bool, 2: bool}>
   *   The environment, core role and expected visibility.
   *
   * @todo UHF-13643 Remove when AI title suggestion is enabled in production.
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

  /**
   * Test that the tone-check plugin is removed when the feature is disabled.
   */
  public function testTonePluginRemovedWhenDisabled(): void {
    $definitions = ['helfi_ai_tone_check' => new CKEditor5PluginDefinition([])];
    $this->createHooks(TRUE, [], toneCheckEnabled: FALSE)->ckeditor5PluginInfoAlter($definitions);
    $this->assertArrayNotHasKey('helfi_ai_tone_check', $definitions);

    $jsSettings = [
      'editor' => [
        'formats' => [
          'full_html' => [
            'editorSettings' => [
              'toolbar' => [
                'items' => ['bold', 'aiToneCheck', 'italic'],
              ],
            ],
          ],
        ],
      ],
    ];
    $this->createHooks(TRUE, [], toneCheckEnabled: FALSE)->editorJsSettingsAlter($jsSettings);
    $this->assertSame(['bold', 'italic'], $jsSettings['editor']['formats']['full_html']['editorSettings']['toolbar']['items']);
  }

}
