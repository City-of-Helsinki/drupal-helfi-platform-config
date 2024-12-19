<?php

declare(strict_types=1);

namespace Drupal\hdbt_admin_tools\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\hdbt_admin_tools\Form\SiteSettings;
use Drupal\hdbt_admin_tools\SelectWidgetBase;

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
class SelectColorPaletteWidget extends SelectWidgetBase {

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
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $element['#default_value'] = !empty($this->getSelectedOptions($items))
      ? $this->getSelectedOptions($items)
      : SiteSettings::getColorPaletteDefaultValue();
    $element['#attached']['library'][] = 'hdbt_admin_tools/select_color_palette';
    $element['#attributes']['class'][] = 'select-color-palette';

    return $element;
  }

}
