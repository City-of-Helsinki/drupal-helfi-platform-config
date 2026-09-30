<?php

declare(strict_types=1);

namespace Drupal\hdbt_admin_tools\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hdbt_admin_tools\SelectWidgetBase;

/**
 * Plugin implementation of the 'design_field_widget' widget.
 */
#[FieldWidget(
  id: 'design_field_widget',
  label: new TranslatableMarkup('Design field widget'),
  field_types: [
    'list_string',
  ],
)]
class SelectDesignWidget extends SelectWidgetBase {

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

    $element['#attached']['library'][] = 'hdbt_admin_tools/select_design';
    $element['#attached']['drupalSettings']['selectDesign']['images'] = $this->designSelectionManager->getImages(
      $this->getFieldName(),
      array_keys($this->getOptions($items->getEntity()))
    );
    $element['#attributes']['class'][] = 'select-design';
    $element['#attributes']['data-select-design'] = $this->getFieldName();

    return $element;
  }

}
