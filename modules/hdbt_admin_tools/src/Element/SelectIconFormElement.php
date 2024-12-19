<?php

declare(strict_types=1);

namespace Drupal\hdbt_admin_tools\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Select;
use Drupal\hdbt_admin_tools\Plugin\Field\FieldType\SelectIcon;

/**
 * Provides a Select icon form element.
 *
 * @FormElement("select_icon_element")
 */
class SelectIconFormElement extends Select {

  /**
   * {@inheritdoc}
   */
  public static function processSelect(&$element, FormStateInterface $form_state, &$complete_form): array {
    // Set icons for the options.
    $element['#options'] = ['' => t('- None -')] + SelectIcon::loadIcons();
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function preRenderSelect($element): array {
    $element = parent::preRenderSelect($element);

    // Set attributes to include the select_icon library and settings.
    $element['#theme'] = 'select_icon_widget';
    $element['#attributes']['class'][] = 'select-icon';

    return $element;
  }

}
