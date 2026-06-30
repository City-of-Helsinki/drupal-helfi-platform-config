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
 *
 * The content types the button is offered on are read from the
 * `helfi_ai.settings:seo_title_bundles` config, so sites can adjust them
 * through configuration without a code change.
 */
final class TitleSuggestionFormAlter {

  use StringTranslationTrait;

  /**
   * Permission required to use the title suggester.
   */
  private const PERMISSION = 'use helfi ai title suggestion';

  public function __construct(
    private readonly AccountInterface $currentUser,
    private readonly ConfigFactoryInterface $configFactory,
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

    $form['title']['helfi_ai_suggest'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['helfi-ai-title-suggest']],
      // Render just below the title input.
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
   * AJAX callback: builds suggestions from live form state, shows them modally.
   *
   * This is a static method because Drupal serializes the form (and its #ajax
   * callbacks) into the cache, so the callback must be a plain callable rather
   * than a bound service instance. It therefore resolves its collaborators
   * from the container, mirroring the AI summary widget.
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
    $translation = \Drupal::translation();
    $title = (string) $translation->translate('Suggested titles', [], ['context' => 'helfi_ai']);
    $response = new AjaxResponse();

    $entity = PreviewEntityBuilder::fromFormState($form, $form_state);
    if (!$entity instanceof ContentEntityInterface) {
      return $response->addCommand(new OpenModalDialogCommand(
        $title,
        self::message((string) $translation->translate('Could not read the page content. Please try again.', [], ['context' => 'helfi_ai'])),
        self::dialogOptions(),
      ));
    }

    $suggestions = \Drupal::service(AiTitleSuggester::class)
      ->suggest($entity, $entity->language()->getId());

    if (!$suggestions) {
      return $response->addCommand(new OpenModalDialogCommand(
        $title,
        self::message((string) $translation->translate('Could not generate title suggestions. Add some page content and make sure the AI provider is configured.', [], ['context' => 'helfi_ai'])),
        self::dialogOptions(),
      ));
    }

    return $response->addCommand(new OpenModalDialogCommand(
      $title,
      self::suggestionsContent($suggestions),
      self::dialogOptions(),
    ));
  }

  /**
   * Standard dialog options for the title suggester modals.
   *
   * Uses the Drupal 11 `classes` syntax (not the deprecated `dialogClass`) to
   * add a styling hook on top of the admin theme's default dialog chrome.
   *
   * @return array<string, mixed>
   *   jQuery UI dialog options.
   */
  private static function dialogOptions(): array {
    return [
      'width' => '40rem',
      'classes' => ['ui-dialog' => 'helfi-ai-title-dialog'],
    ];
  }

  /**
   * Builds the modal body: a radio option box plus Apply / Cancel actions.
   *
   * Each radio carries a candidate title as its value. The title_suggest
   * behavior reads the selected radio on Apply, fills the title field and
   * closes the dialog; Cancel just closes it. Apply/Cancel are plain buttons
   * handled client-side — applying a title is a pure DOM update, so no second
   * server round-trip is needed.
   *
   * @param string[] $suggestions
   *   The title candidates.
   *
   * @return array<string, mixed>
   *   A render array for the modal body.
   */
  private static function suggestionsContent(array $suggestions): array {
    $translation = \Drupal::translation();

    $radios = [];
    foreach ($suggestions as $i => $suggestion) {
      $id = 'helfi-ai-title-option-' . $i;
      $radios['option_' . $i] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['form-item', 'helfi-ai-title-option']],
        'input' => [
          '#type' => 'html_tag',
          '#tag' => 'input',
          '#attributes' => [
            'type' => 'radio',
            'name' => 'helfi_ai_title',
            'id' => $id,
            // Match Claro's radio classes so the admin theme styles these the
            // same as real Form API radios (which can't expand in a detached
            // modal render array).
            'class' => ['form-radio', 'form-boolean', 'form-boolean--type-radio', 'helfi-ai-title-radio'],
            'value' => $suggestion,
          // Pre-select the first candidate so Apply always has a selection.
          ] + ($i === 0 ? ['checked' => 'checked'] : []),
        ],
        'label' => [
          '#type' => 'html_tag',
          '#tag' => 'label',
          '#attributes' => ['for' => $id, 'class' => ['form-item__label', 'option', 'helfi-ai-title-label']],
          '#value' => $suggestion . ' ',
          // Character count as a subtle length hint next to each candidate.
          'count' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#attributes' => ['class' => ['helfi-ai-title-count']],
            '#value' => '(' . mb_strlen($suggestion) . ')',
          ],
        ],
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['helfi-ai-title-suggestions']],
      '#attached' => ['library' => ['helfi_ai/title_suggest']],
      'options' => [
        '#type' => 'html_tag',
        '#tag' => 'fieldset',
        '#attributes' => ['class' => ['helfi-ai-title-options']],
        'legend' => [
          '#type' => 'html_tag',
          '#tag' => 'legend',
          '#attributes' => ['class' => ['visually-hidden']],
          '#value' => $translation->translate('Suggested titles', [], ['context' => 'helfi_ai']),
        ],
      ] + $radios,
      'actions' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['form-actions', 'helfi-ai-title-actions']],
        'apply' => [
          '#type' => 'html_tag',
          '#tag' => 'button',
          '#value' => $translation->translate('Apply', [], ['context' => 'helfi_ai']),
          '#attributes' => [
            'type' => 'button',
            'class' => ['button', 'button--primary', 'helfi-ai-title-apply'],
          ],
        ],
        'cancel' => [
          '#type' => 'html_tag',
          '#tag' => 'button',
          '#value' => $translation->translate('Cancel', [], ['context' => 'helfi_ai']),
          '#attributes' => [
            'type' => 'button',
            'class' => ['button', 'button--secondary', 'helfi-ai-title-cancel'],
          ],
        ],
      ],
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
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $text,
    ];
  }

}
