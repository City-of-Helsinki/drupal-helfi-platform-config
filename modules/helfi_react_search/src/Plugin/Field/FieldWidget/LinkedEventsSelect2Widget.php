<?php

declare(strict_types=1);

namespace Drupal\helfi_react_search\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\select2\Plugin\Field\FieldWidget\Select2Widget;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Widget for linked events filters.
 */
#[FieldWidget(
  id: "linked_events_select2",
  label: new TranslatableMarkup('Helfi: Linked events select2 widget'),
  field_types: ['string'],
  multiple_values: TRUE,
)]
final class LinkedEventsSelect2Widget extends Select2Widget {

  /**
   * The language manager.
   */
  private LanguageManagerInterface $languageManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    $widget = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $widget->languageManager = $container->get(LanguageManagerInterface::class);
    return $widget;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'endpoint' => 'keyword',
      'query' => '',
    ] + parent::defaultSettings();
  }

  /**
   * Select2 callback for getting an array of currently selected options.
   *
   * @param array $element
   *   The render element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   Key => encoded item, Value => entity label.
   */
  public function getValidSelectedOptions(array $element, FormStateInterface $form_state): array {
    $value = is_array($element['#value']) ? $element['#value'] : [$element['#value']];

    $options = [];
    foreach ($value as $item) {
      $options[$item] = $this->getOptionLabel($item);
    }

    return $options;
  }

  /**
   * Select2 callback for settings the autocomplete route parameters.
   *
   * @param array $element
   *   The render element.
   *
   * @return array
   *   The render element with autocomplete route parameters.
   */
  public static function setAutocompleteRouteParameters(array &$element): array {
    $complete_form = [];
    $element = EntityAutocomplete::processEntityAutocomplete($element, new FormState(), $complete_form);
    $element['#autocomplete_route_name'] = 'helfi_react_search.linked_events.autocomplete';
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $element['#target_type'] = $this->getSetting('endpoint');
    $element['#autocomplete_route_callback'] = self::class . '::setAutocompleteRouteParameters';
    $element['#autocomplete_options_callback'] = [$this, 'getValidSelectedOptions'];
    $element['#selection_settings'] = [
      'query' => $this->getSetting('query'),
      'search_key' => $this->getSetting('search_key'),
    ];
    $element['#autocomplete'] = TRUE;
    $element['#multiple'] = $this->multiple;

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function getOptions(FieldableEntityInterface $entity): array {
    if (!isset($this->options)) {
      $selected_options = [];

      // Get all currently selected options.
      foreach ($entity->get($this->fieldDefinition->getName()) as $item) {
        if ($item->{$this->column} !== NULL) {
          $selected_options[$item->{$this->column}] = $this->getOptionLabel($item->{$this->column});
        }
      }

      $this->options = $selected_options;
    }

    return $this->options;
  }

  /**
   * Get option label from value.
   *
   * Option values are JSON serialized LinkedEventsItem objects.
   *
   * @param string $value
   *   Option element value.
   *
   * @return string
   *   Option element label.
   *
   * @see \Drupal\helfi_react_search\DTO\LinkedEventsItem
   */
  private function getOptionLabel(string $value): string {
    $langcode = $this->languageManager->getCurrentLanguage()->getId();

    // The linked events data is stored as JSON serialized
    // strings so that no API calls needs to made to get the
    // translated labels.
    // See LinkedEventsAutocompleteController.
    $json = json_decode($value);

    return $json->name?->{$langcode} ?: $json->name?->en ?: 'Unknown';
  }

}
