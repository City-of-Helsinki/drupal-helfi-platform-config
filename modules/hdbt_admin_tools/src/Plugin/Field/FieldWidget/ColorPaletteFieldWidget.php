<?php

namespace Drupal\hdbt_admin_tools\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsSelectWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\hdbt_admin_tools\Form\SiteSettings;

/**
 * Plugin implementation of the 'color_palette_field_widget' widget.
 *
 * @FieldWidget(
 *   id = "color_palette_field_widget",
 *   module = "hdbt_admin_tools",
 *   label = @Translation("Color palette field widget"),
 *   field_types = {
 *     "list_string"
 *   },
 *   multiple_values = FALSE
 * )
 */
class ColorPaletteFieldWidget extends OptionsSelectWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(
    FieldItemListInterface $items,
    $delta,
    array $element,
    array &$form,
    FormStateInterface $form_state
  ): array {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $element['#type'] = 'select2';
    $element['#cardinality'] = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality();
    $element['#select2'] = [
      'width' => $this->getSetting('width') ?? '100%',
    ];
    $element['#options'] = $this->getOptions($items->getEntity());
    $element['#default_value'] = !empty($this->getSelectedOptions($items))
      ? $this->getSelectedOptions($items)
      : SiteSettings::getColorPaletteDefaultValue();
    $element['#attached']['library'][] = 'hdbt_admin_tools/color_palette_selection';
    $element['#attributes']['class'][] = 'tom-select-color-palette';
    $element['#attributes']['data-color-palette-select'] = $this->getFieldName();

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function validateElement(
    array $element,
    FormStateInterface $form_state
  ): void {

    // Massage submitted form values.
    // Drupal\Core\Field\WidgetBase::submit() expects values as
    // an array of values keyed by delta first, then by column, while our
    // widgets return the opposite.
    if (is_array($element['#value'])) {
      $values = array_values($element['#value']);
    }
    else {
      $values = [$element['#value']];
    }

    // Filter out the '' option. Use a strict comparison, because
    // 0 == 'any string'.
    $index = array_search('', $values, TRUE);
    if ($index !== FALSE) {
      unset($values[$index]);
    }

    // Design field widget cannot handle multiple values.
    $item[$element['#key_column']] = $element['#value'];
    $form_state->setValueForElement($element, $item);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEmptyLabel(): void {}

  /**
   * Get field name and convert it to a more suitable name for our needs.
   *
   * @return string
   *   Returns field name.
   */
  protected function getFieldName(): string {
    $field_name = $this->fieldDefinition->getName();
    if ($field_name) {
      $name = str_replace('field_', '', $field_name);
      return str_replace('_', '-', $name);
    }
    return $this->fieldDefinition->getName();
  }

}
