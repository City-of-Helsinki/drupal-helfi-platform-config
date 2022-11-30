<?php

namespace Drupal\select2_icon\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\select2\Element\Select2;
use Drupal\select2_icon\Plugin\Field\FieldType\Select2Icon;

/**
 * Provides an Select2 Icon form element.
 *
 * @FormElement("select2_icon_element")
 */
class Select2IconFormElement extends Select2 {

  /**
   * {@inheritdoc}
   */
  public static function processSelect(&$element, FormStateInterface $form_state, &$complete_form): array {
    // Set icons for the options.
    $element['#options'] = [$element['#empty_value'] => ''] + Select2Icon::loadIcons();
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function preRenderSelect($element): array {
    $element = parent::preRenderSelect($element);

    // Set attributes to include the select2_icon library and settings.
    $element['#theme'] = 'select2_icon_widget';
    $element['#attributes']['class'][] = 'select2-icon';

    return $element;
  }

}
