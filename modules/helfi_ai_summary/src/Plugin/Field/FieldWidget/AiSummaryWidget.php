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

/**
 * Widget for the AI summary field with three-state AJAX flow.
 *
 * Modes:
 *   - initial: no summary, only Generate button.
 *   - draft: editable textarea + Accept / Reject.
 *   - accepted: editable textarea + Regenerate.
 *
 * The widget tracks mode in form state. The textarea value is driven by
 * Drupal's normal user-input handling (via #default_value), which means
 * user edits persist naturally and get saved via the standard field
 * extraction when the node form is submitted. AJAX button handlers also
 * write fresh values into user input so rebuilds reflect AI output and
 * rejection reverts.
 */
#[
  FieldWidget(
    id: "ai_summary",
    label: new TranslatableMarkup("AI Summary"),
    field_types: ["text_long"],
  ),
]
final class AiSummaryWidget extends WidgetBase
{
  /**
   * Text format used for AI summary content (allows <ul>, <li>, links).
   */
  private const TEXT_FORMAT = "minimal";

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(
    FieldDefinitionInterface $field_definition,
  ): bool {
    return $field_definition->getName() === "field_ai_summary";
  }

  /**
   * {@inheritdoc}
   */
  /**
   * {@inheritdoc}
   *
   * Use form state as authoritative for the saved value. The widget renders
   * conditionally per mode, so the textarea isn't always present in user
   * input. The state is updated by buttonSubmit() on every action and reflects
   * exactly what the user accepted, generated, or reverted to.
   */
  public function extractFormValues(
    FieldItemListInterface $items,
    array $form,
    FormStateInterface $form_state,
  ) {
    parent::extractFormValues($items, $form, $form_state);
    $field_name = $this->fieldDefinition->getName();
    $state = self::readState($form_state, $field_name, 0);
    if ($state !== null) {
      $items->setValue([
        [
          "value" => $state["value"],
          "format" => self::TEXT_FORMAT,
        ],
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(
    FieldItemListInterface $items,
    $delta,
    array $element,
    array &$form,
    FormStateInterface $form_state,
  ): array {
    $field_name = $items->getFieldDefinition()->getName();
    $wrapper_id =
      "ai-summary-" . str_replace("_", "-", $field_name) . "-" . $delta;

    $saved_value = $items[$delta]->value ?? "";
    $state = self::initState($form_state, $field_name, $delta, $saved_value);
    $mode = $state["mode"];

    $element["#prefix"] = '<div id="' . $wrapper_id . '">';
    $element["#suffix"] = "</div>";

    if (!empty($state["error"])) {
      $element["error"] = [
        "#type" => "html_tag",
        "#tag" => "p",
        "#value" => $state["error"],
        "#attributes" => ["class" => ["messages", "messages--error"]],
        "#weight" => -20,
      ];
      self::updateState($form_state, $field_name, $delta, ["error" => ""]);
    }

    if ($mode !== "initial") {
      $element["value"] = [
        "#type" => "text_format",
        "#title" => $this->t(
          "AI summary",
          options: ["context" => "helfi_ai_summary"],
        ),
        "#title_display" => "invisible",
        "#default_value" => $state["value"],
        "#format" => self::TEXT_FORMAT,
        "#allowed_formats" => [self::TEXT_FORMAT],
        "#rows" => 6,
      ];
    }

    $element += $this->buildButtons($mode, $field_name, $delta, $wrapper_id);

    $element["mode_description"] = [
      "#type" => "html_tag",
      "#tag" => "div",
      "#value" => $this->modeDescription($mode),
      "#attributes" => ["class" => ["description", "form-item__description"]],
      "#weight" => 100,
    ];

    return $element;
  }

  /**
   * Helper text shown below the buttons for each mode.
   */
  private function modeDescription(string $mode): string
  {
    return match ($mode) {
      "draft" => (string) $this->t(
        "You can edit the text before accepting.",
        options: ["context" => "helfi_ai_summary"],
      ),
      "accepted" => (string) $this->t(
        "The summary is saved with the page. You can create a new suggestion at any time.",
        options: ["context" => "helfi_ai_summary"],
      ),
      default => (string) $this->t(
        "AI generates a 4–6 bullet point summary of the page. You can edit the summary before accepting.",
        options: ["context" => "helfi_ai_summary"],
      ),
    };
  }

  /**
   * Build the action buttons for the current mode.
   */
  private function buildButtons(
    string $mode,
    string $field_name,
    int $delta,
    string $wrapper_id,
  ): array {
    $ctx = ["context" => "helfi_ai_summary"];
    $buttons = [];
    if ($mode === "initial") {
      $buttons["generate"] = $this->button(
        "generate",
        $this->t("Generate AI summary", options: $ctx),
        $field_name,
        $delta,
        $wrapper_id,
        true,
      );
    } elseif ($mode === "draft") {
      $buttons["accept"] = $this->button(
        "accept",
        $this->t("Accept", options: $ctx),
        $field_name,
        $delta,
        $wrapper_id,
      );
      $buttons["accept"]["#button_type"] = "primary";
      $buttons["reject"] = $this->button(
        "reject",
        $this->t("Reject", options: $ctx),
        $field_name,
        $delta,
        $wrapper_id,
      );
    } elseif ($mode === "accepted") {
      $buttons["regenerate"] = $this->button(
        "generate",
        $this->t("Regenerate AI summary", options: $ctx),
        $field_name,
        $delta,
        $wrapper_id,
        true,
      );
      $buttons["regenerate"]["#attributes"]["class"][] = "button--link";
    }
    return $buttons;
  }

  /**
   * Build a single AJAX button.
   */
  private function button(
    string $action,
    TranslatableMarkup $label,
    string $field_name,
    int $delta,
    string $wrapper_id,
    bool $with_progress = false,
  ): array {
    $button = [
      "#type" => "button",
      "#value" => $label,
      "#name" => "ai_summary_" . $action . "_" . $field_name . "_" . $delta,
      "#executes_submit_callback" => true,
      "#limit_validation_errors" => [],
      "#submit" => [[static::class, "buttonSubmit"]],
      "#ajax" => [
        "callback" => [static::class, "ajaxCallback"],
        "wrapper" => $wrapper_id,
      ],
    ];
    if ($with_progress) {
      $button["#ajax"]["progress"] = [
        "type" => "throbber",
        "message" => $this->t(
          "AI is creating a summary…",
          options: ["context" => "helfi_ai_summary"],
        ),
      ];
    }
    return $button;
  }

  /**
   * Submit handler: mutates widget state before the form is rebuilt.
   */
  public static function buttonSubmit(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    $trigger = $form_state->getTriggeringElement();
    if (
      !preg_match(
        '/^ai_summary_(generate|accept|reject)_(.+)_(\d+)$/',
        $trigger["#name"] ?? "",
        $m,
      )
    ) {
      return;
    }
    [, $action, $field_name, $delta] = $m;
    $delta = (int) $delta;

    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof ContentEntityFormInterface) {
      return;
    }
    $entity = $form_object->getEntity();

    switch ($action) {
      case "generate":
        $generator = \Drupal::service(AiSummaryGenerator::class);
        $summary = $generator->generate($entity, $entity->language()->getId());
        if ($summary !== null) {
          self::updateState($form_state, $field_name, $delta, [
            "mode" => "draft",
            "value" => $summary,
          ]);
          self::writeUserInputValue($form_state, $field_name, $delta, $summary);
        } else {
          self::updateState($form_state, $field_name, $delta, [
            "error" => (string) t(
              "Could not generate summary. Ensure the content is saved and the AI provider is configured.",
              options: ["context" => "helfi_ai_summary"],
            ),
          ]);
        }
        break;

      case "accept":
        // text_format element submits as field[delta][value][value] and
        // field[delta][value][format]; we only care about the HTML.
        $input = $form_state->getUserInput();
        $edited =
          NestedArray::getValue($input, [
            $field_name,
            $delta,
            "value",
            "value",
          ]) ?? "";
        self::updateState($form_state, $field_name, $delta, [
          "mode" => "accepted",
          "value" => (string) $edited,
        ]);
        break;

      case "reject":
        $state = self::readState($form_state, $field_name, $delta) ?? [];
        $original = $state["original"] ?? "";
        self::updateState($form_state, $field_name, $delta, [
          "mode" => $original !== "" ? "accepted" : "initial",
          "value" => $original,
        ]);
        self::writeUserInputValue($form_state, $field_name, $delta, $original);
        break;
    }

    $form_state->setRebuild(true);
  }

  /**
   * AJAX callback: returns the rebuilt widget element to the browser.
   */
  public static function ajaxCallback(
    array &$form,
    FormStateInterface $form_state,
  ): AjaxResponse {
    $trigger = $form_state->getTriggeringElement();
    $parents = array_slice($trigger["#array_parents"], 0, -1);
    $element = NestedArray::getValue($form, $parents);

    preg_match('/id="([^"]+)"/', $element["#prefix"] ?? "", $matches);
    $wrapper_id = $matches[1] ?? "";

    return new AjaxResponse()->addCommand(
      new ReplaceCommand("#" . $wrapper_id, $element),
    );
  }

  /**
   * Form state key for the widget's whole state bundle.
   */
  private static function stateKey(string $field_name, int $delta): string
  {
    return "ai_summary_state_" . $field_name . "_" . $delta;
  }

  /**
   * Read the current state, or NULL if not initialized.
   */
  private static function readState(
    FormStateInterface $form_state,
    string $field_name,
    int $delta,
  ): ?array {
    return $form_state->get(self::stateKey($field_name, $delta));
  }

  /**
   * Initialize state on first render, or return existing state.
   */
  private static function initState(
    FormStateInterface $form_state,
    string $field_name,
    int $delta,
    string $saved_value,
  ): array {
    $key = self::stateKey($field_name, $delta);
    $state = $form_state->get($key);
    if ($state === null) {
      $state = [
        "mode" => $saved_value !== "" ? "accepted" : "initial",
        "value" => $saved_value,
        "original" => $saved_value,
        "error" => "",
      ];
      $form_state->set($key, $state);
    }
    return $state;
  }

  /**
   * Merge updates into the widget state.
   */
  private static function updateState(
    FormStateInterface $form_state,
    string $field_name,
    int $delta,
    array $changes,
  ): void {
    $key = self::stateKey($field_name, $delta);
    $state = $form_state->get($key) ?? [];
    $form_state->set($key, $changes + $state);
  }

  /**
   * Overwrite raw user input so the form rebuild shows a new textarea value.
   */
  private static function writeUserInputValue(
    FormStateInterface $form_state,
    string $field_name,
    int $delta,
    string $value,
  ): void {
    $input = $form_state->getUserInput();
    // Match the text_format element's nested input structure.
    NestedArray::setValue(
      $input,
      [$field_name, $delta, "value", "value"],
      $value,
    );
    NestedArray::setValue(
      $input,
      [$field_name, $delta, "value", "format"],
      self::TEXT_FORMAT,
    );
    $form_state->setUserInput($input);
  }
}
