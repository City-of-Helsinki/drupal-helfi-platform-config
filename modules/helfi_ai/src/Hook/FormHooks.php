<?php

declare(strict_types=1);

namespace Drupal\helfi_ai\Hook;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_ai\PreviewEntityBuilder;
use Drupal\helfi_ai\Service\AiGenerator;
use Drupal\node\NodeInterface;

/**
 * Form hooks for AI features.
 */
final class FormHooks {

  use DependencySerializationTrait;

  public function __construct(
    private readonly AccountInterface $currentUser,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly AiGenerator $generator,
  ) {
  }

  /**
   * Check if a feature is enabled.
   *
   * @param string $feature
   *   The feature name as a string.
   *
   * @return bool
   *   Returns true or false.
   */
  private function featureEnabled(string $feature): bool {
    return (bool) $this->configFactory->get('helfi_ai.settings')->get($feature);
  }

  /**
   * Checks if the widget should be shown for this form.
   *
   * @param array<mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   *
   * @return bool
   *   TRUE if form is valid.
   */
  private function isValidForm(array $form, FormStateInterface $formState): bool {
    $form_object = $formState->getFormObject();

    if (!$form_object instanceof ContentEntityFormInterface) {
      return FALSE;
    }

    if (!$this->featureEnabled('enable_seo_title')) {
      return FALSE;
    }

    $entity = $form_object->getEntity();

    if (!$entity instanceof NodeInterface) {
      return FALSE;
    }
    $bundles = $this->configFactory->get('helfi_ai.settings')->get('seo_title_bundles') ?? [];

    if (!in_array($entity->bundle(), $bundles, TRUE)) {
      return FALSE;
    }
    if (!isset($form['title']['widget'][0]['value'])) {
      return FALSE;
    }

    if (!$this->currentUser->hasPermission('use helfi ai title suggestion')) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * AJAX callback for the suggest button: opens a suggestions or error modal.
   *
   * @param array<string, mixed> $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Response opening a modal with the suggestions or an error message.
   */
  public function buildSuggestionResponse(array &$form, FormStateInterface $form_state): AjaxResponse {
    $title = (string) new TranslatableMarkup('Suggested titles', options: ['context' => 'Helfi AI']);
    $response = new AjaxResponse();

    $dialogOptions = [
      'width' => '40rem',
      'classes' => ['ui-dialog' => 'helfi-ai-dialog'],
    ];

    $entity = PreviewEntityBuilder::fromFormState($form, $form_state);

    if (!$entity instanceof ContentEntityInterface) {
      return $response->addCommand(new OpenModalDialogCommand(
        $title,
        $this->message((string) new TranslatableMarkup('Could not read the page content. Please try again.', options: ['context' => 'Helfi AI'])),
        $dialogOptions,
      ));
    }

    $suggestions = $this->generator->suggestTitles($entity);

    if (!$suggestions) {
      return $response->addCommand(new OpenModalDialogCommand(
        $title,
        $this->message((string) new TranslatableMarkup('Could not generate title suggestions. Add some page content and make sure the AI provider is configured.', options: ['context' => 'Helfi AI'])),
        $dialogOptions,
      ));
    }

    return $response->addCommand(new OpenModalDialogCommand(
      $title,
      [
        '#theme' => 'ai_title_suggestions',
        '#suggestions' => array_values($suggestions),
        '#attached' => ['library' => ['helfi_ai/title_suggest']],
      ],
      $dialogOptions,
    ));
  }

  /**
   * Wraps a plain message string in a render array for a modal body.
   *
   * @param string $text
   *   The message text.
   *
   * @return array<string, mixed>
   *   A render array.
   */
  private function message(string $text): array {
    return [
      '#theme' => 'ai_message',
      '#text' => $text,
    ];
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for node forms.
   *
   * @param array<string, mixed> $form
   *   The node form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param string $form_id
   *   The id of the node form being altered.
   */
  #[Hook('form_node_form_alter')]
  public function nodeFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    if (!$this->isValidForm($form, $form_state)) {
      return;
    }

    $form['title']['#attributes']['class'][] = 'ai-title';

    $form['title']['helfi_ai_suggest'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['ai-suggest']],
      '#weight' => ($form['title']['widget'][0]['value']['#weight'] ?? 0) + 0.5,
      'button' => [
        '#type' => 'button',
        '#value' => new TranslatableMarkup('Generate SEO title with AI', options: ['context' => 'Helfi AI']),
        '#name' => 'helfi_ai_suggest_title',
        '#attributes' => ['class' => ['button--small']],
        '#ajax' => [
          'callback' => [$this, 'buildSuggestionResponse'],
          'event' => 'click',
          'progress' => [
            'type' => 'throbber',
            'message' => new TranslatableMarkup('Generating title suggestions…', options: ['context' => 'Helfi AI']),
          ],
        ],
        '#attached' => ['library' => ['helfi_ai/title_suggest']],
      ],
    ];
  }

  /**
   * Implements hook_ckeditor5_plugin_info_alter().
   *
   * @param array<string, \Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition> $plugin_definitions
   *   The plugin definitions.
   */
  #[Hook('ckeditor5_plugin_info_alter')]
  public function ckeditor5PluginInfoAlter(array &$plugin_definitions): void {
    if ($this->featureEnabled('enable_tone_check')) {
      return;
    }
    // Remove the AI tone check plugin definition.
    unset($plugin_definitions['helfi_ai_tone_check']);
  }

  /**
   * Implements hook_editor_js_settings_alter().
   *
   * @param array<string, mixed> $settings
   *   The editor JS settings.
   */
  #[Hook('editor_js_settings_alter')]
  public function editorJsSettingsAlter(array &$settings): void {
    if ($this->featureEnabled('enable_tone_check')) {
      return;
    }
    // Remove the CKEditor toolbar item for AI tone check plugin.
    foreach ($settings['editor']['formats'] ?? [] as &$format) {
      $items = &$format['editorSettings']['toolbar']['items'];
      if (is_array($items)) {
        $items = array_values(array_filter($items, fn($item) => $item !== 'aiToneCheck'));
      }
    }
  }

}
