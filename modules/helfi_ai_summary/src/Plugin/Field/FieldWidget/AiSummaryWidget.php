<?php

declare(strict_types=1);

namespace Drupal\helfi_ai_summary\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_ai_summary\Service\AiSummaryGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Widget for the AI summary field with AJAX-powered generation.
 */
#[FieldWidget(
  id: 'ai_summary',
  label: new TranslatableMarkup('AI Summary'),
  field_types: ['string_long'],
)]
final class AiSummaryWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    private readonly AiSummaryGenerator $generator,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get(AiSummaryGenerator::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    return $field_definition->getName() === 'field_ai_summary';
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $field_name = $items->getFieldDefinition()->getName();
    $wrapper_id = 'ai-summary-' . str_replace('_', '-', $field_name) . '-' . $delta;
    $state_key = 'ai_summary_generated_' . $field_name . '_' . $delta;
    $error_key = 'ai_summary_error_' . $field_name . '_' . $delta;

    $value = $form_state->get($state_key) ?? ($items[$delta]->value ?? '');

    $element['#prefix'] = '<div id="' . $wrapper_id . '">';
    $element['#suffix'] = '</div>';

    if ($error = $form_state->get($error_key)) {
      $element['error'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $error,
        '#attributes' => ['class' => ['messages', 'messages--error']],
      ];
      $form_state->set($error_key, NULL);
    }

    $element['value'] = [
      '#type' => 'textarea',
      '#title' => $this->t('AI Summary'),
      '#default_value' => $value,
      '#description' => $this->t('One bullet point per line, no list markers. Save the content first so generation uses the latest version.'),
      '#rows' => 6,
    ];

    $element['generate'] = [
      '#type' => 'button',
      '#value' => $this->t('Generate summary'),
      '#name' => 'ai_summary_generate_' . $field_name . '_' . $delta,
      '#limit_validation_errors' => [],
      '#submit' => [[static::class, 'generateSubmit']],
      '#ajax' => [
        'callback' => [static::class, 'generateAjaxCallback'],
        'wrapper' => $wrapper_id,
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Generating summary…'),
        ],
      ],
    ];

    return $element;
  }

  /**
   * Submit handler for the Generate button.
   *
   * Calls the AI service and stores the result in form state for re-render.
   */
  public static function generateSubmit(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $name = $trigger['#name'];

    // Parse field name and delta from button name: ai_summary_generate_{field}_{delta}
    $suffix = substr($name, strlen('ai_summary_generate_'));
    $last_underscore = strrpos($suffix, '_');
    if ($last_underscore === FALSE) {
      return;
    }
    $field_name = substr($suffix, 0, $last_underscore);
    $delta = (int) substr($suffix, $last_underscore + 1);

    $state_key = 'ai_summary_generated_' . $field_name . '_' . $delta;
    $error_key = 'ai_summary_error_' . $field_name . '_' . $delta;

    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof ContentEntityFormInterface) {
      $form_state->set($error_key, (string) t('Cannot generate summary outside an entity form.'));
      $form_state->setRebuild(TRUE);
      return;
    }

    $entity = $form_object->getEntity();

    /** @var \Drupal\helfi_ai_summary\Service\AiSummaryGenerator $generator */
    $generator = \Drupal::service(AiSummaryGenerator::class);
    $summary = $generator->generate($entity, $entity->language()->getId());

    if ($summary === NULL) {
      $form_state->set($error_key, (string) t('Could not generate summary. Ensure the content is saved and the AI provider is configured.'));
    }
    else {
      $form_state->set($state_key, $summary);
    }

    $form_state->setRebuild(TRUE);
  }

  /**
   * AJAX callback: returns the rebuilt widget element to replace the wrapper.
   */
  public static function generateAjaxCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
    $trigger = $form_state->getTriggeringElement();
    $parents = array_slice($trigger['#array_parents'], 0, -1);
    $element = NestedArray::getValue($form, $parents);

    // Extract wrapper ID from the #prefix set in formElement().
    preg_match('/id="([^"]+)"/', $element['#prefix'] ?? '', $matches);
    $wrapper_id = $matches[1] ?? '';

    return (new AjaxResponse())->addCommand(
      new ReplaceCommand('#' . $wrapper_id, $element),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state): array {
    $massaged = [];
    foreach ($values as $delta => $value) {
      $massaged[$delta] = ['value' => $value['value'] ?? ''];
    }
    return $massaged;
  }

}
