<?php

declare(strict_types=1);

namespace Drupal\hdbt_admin_tools\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'link_target_icon_field_widget' widget.
 *
 * @FieldWidget(
 *   id = "link_target_icon_field_widget",
 *   label = @Translation("Link with target and icon"),
 *   field_types = {
 *     "link"
 *   }
 * )
 */
class LinkTargetIconFieldWidget extends LinkTargetFieldWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) : array {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $item = parent::getLinkItem($items, $delta);
    $options = $item->get('options')->getValue();

    $element['options']['icon'] = [
      '#type' => 'select_icon_element',
      '#title' => $this->t('Icon'),
      '#default_value' => $options['icon'] ?? NULL,
      '#weight' => 99,
    ];

    return $element;
  }

}
