<?php

declare(strict_types=1);

namespace Drupal\hdbt_admin_tools\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\text\Plugin\Field\FieldWidget\TextareaWidget;

/**
 * Plugin implementation of the 'formatted_text_character_counter' widget.
 *
 * @FieldWidget(
 *   id = "formatted_text_character_counter",
 *   label = @Translation("Text area (formatted text, character counter)"),
 *   field_types = {
 *     "text_long",
 *   }
 * )
 */
class FormattedTextCharacterCounterWidget extends TextareaWidget {

  use CharacterCounterFieldWidgetTrait;

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['#character_counter'] = TRUE;
    $element['#counter_step'] = $this->getSetting('counter_step');
    $element['#counter_total'] = $this->getSetting('counter_total');
    return $element;
  }

}
