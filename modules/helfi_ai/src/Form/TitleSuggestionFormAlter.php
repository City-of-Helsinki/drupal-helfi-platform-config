<?php

declare(strict_types=1);

namespace Drupal\helfi_ai\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\helfi_ai\PreviewEntityBuilder;
use Drupal\helfi_ai\Service\AiTitleSuggester;
use Drupal\node\NodeInterface;

/**
 * Adds an AI "Suggest SEO title" button next to the node title field.
 *
 * An AJAX button beside the title builds the unsaved node from the current form
 * values, asks {@see AiTitleSuggester} for a few GEO/SEO-optimized title
 * candidates and shows them in a modal. Picking one fills the title field
 * client-side (see js/ai-title-suggest.js); the editor can still edit it.
 *
 * The button is a plain (non-submit) AJAX button on purpose, for the same
 * reason as the AI summary widget: its callback runs regardless of validation
 * and sees the full, un-pruned form values, so the unsaved entity (including
 * unsaved paragraphs) can be rebuilt in memory.
 */
class TitleSuggestionFormAlter {

  use StringTranslationTrait;

  /**
   * Permission required to use the title suggester.
   */
  private const PERMISSION = 'use helfi ai title suggestion';

  public function __construct(
    private readonly AccountInterface $currentUser,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly AiTitleSuggester $aiTitleSuggester,
    TranslationInterface $stringTranslation,
  ) {
    $this->setStringTranslation($stringTranslation);
  }

  /**
   * Alters a node form to add the title suggestion button.
   *
   * @param array<string, mixed> $form
   *   The node form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  public function alter(array &$form, FormStateInterface $form_state): void {
    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof ContentEntityFormInterface) {
      return;
    }
    $entity = $form_object->getEntity();
    if (!$entity instanceof NodeInterface) {
      return;
    }
    $bundles = $this->configFactory->get('helfi_ai.settings')->get('seo_title_bundles') ?? [];
    if (!in_array($entity->bundle(), $bundles, TRUE)) {
      return;
    }
    // The title base field uses the standard string widget; bail if it is not
    // present (e.g. removed from the form display).
    if (!isset($form['title']['widget'][0]['value'])) {
      return;
    }
    if (!$this->currentUser->hasPermission(self::PERMISSION)) {
      return;
    }

    $form['title']['#attributes']['class'][] = 'helfi-ai-title';

    $form['title']['helfi_ai_suggest'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['helfi-ai-suggest']],
      '#weight' => ($form['title']['widget'][0]['value']['#weight'] ?? 0) + 0.5,
      'button' => [
        '#type' => 'button',
        '#value' => $this->t('Suggest SEO title', options: ['context' => 'helfi_ai']),
        '#name' => 'helfi_ai_suggest_title',
        '#attributes' => ['class' => ['button--small']],
        '#ajax' => [
          'callback' => [self::class, 'ajaxCallback'],
          'event' => 'click',
          'progress' => [
            'type' => 'throbber',
            'message' => $this->t('Generating title suggestions…', options: ['context' => 'helfi_ai']),
          ],
        ],
        '#attached' => ['library' => ['helfi_ai/title_suggest']],
      ],
    ];
  }

  /**
   * AJAX callback for the suggest button.
   *
   * Kept static because Drupal serializes #ajax callbacks into the form cache,
   * so the callback must be a plain callable rather than a bound instance. It
   * immediately delegates to the service, where the work runs with injected
   * dependencies.
   *
   * @param array<string, mixed> $form
   *   The (rebuilt) form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Response opening a modal with the suggestions or an error message.
   */
  public static function ajaxCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
    return \Drupal::service(self::class)->buildSuggestionResponse($form, $form_state);
  }

  /**
   * Builds the AJAX response: a suggestions modal or an error modal.
   *
   * @param array<string, mixed> $form
   *   The (rebuilt) form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Response opening a modal with the suggestions or an error message.
   */
  public function buildSuggestionResponse(array &$form, FormStateInterface $form_state): AjaxResponse {
    $title = (string) $this->t('Suggested titles', options: ['context' => 'helfi_ai']);
    $response = new AjaxResponse();

    $entity = PreviewEntityBuilder::fromFormState($form, $form_state);
    if (!$entity instanceof ContentEntityInterface) {
      return $response->addCommand(new OpenModalDialogCommand(
        $title,
        self::message((string) $this->t('Could not read the page content. Please try again.', options: ['context' => 'helfi_ai'])),
        self::dialogOptions(),
      ));
    }

    $suggestions = $this->aiTitleSuggester->suggest($entity);

    if (!$suggestions) {
      return $response->addCommand(new OpenModalDialogCommand(
        $title,
        self::message((string) $this->t('Could not generate title suggestions. Add some page content and make sure the AI provider is configured.', options: ['context' => 'helfi_ai'])),
        self::dialogOptions(),
      ));
    }

    return $response->addCommand(new OpenModalDialogCommand(
      $title,
      $this->suggestionsContent($suggestions),
      self::dialogOptions(),
    ));
  }

  /**
   * Standard dialog options for the title suggester modals.
   *
   * @return array<string, mixed>
   *   jQuery UI dialog options.
   */
  private static function dialogOptions(): array {
    return [
      'width' => '40rem',
      'classes' => ['ui-dialog' => 'helfi-ai-dialog'],
    ];
  }

  /**
   * Builds the modal body: a radio option box plus Apply / Cancel actions.
   *
   * @param string[] $suggestions
   *   The title candidates.
   *
   * @return array<string, mixed>
   *   A render array for the modal body.
   */
  private function suggestionsContent(array $suggestions): array {
    return [
      '#theme' => 'helfi_ai_title_suggestions',
      '#suggestions' => array_values($suggestions),
      '#attached' => ['library' => ['helfi_ai/title_suggest']],
    ];
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
  private static function message(string $text): array {
    return [
      '#theme' => 'helfi_ai_message',
      '#text' => $text,
    ];
  }

}
