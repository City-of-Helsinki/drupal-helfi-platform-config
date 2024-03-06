<?php

declare(strict_types=1);

namespace Drupal\hdbt_admin_tools\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Plugin\Field\FieldWidget\StringTextfieldWidget;

/**
 * Plugin implementation of the 'textarea_character_counter' widget.
 *
 * @FieldWidget(
 *   id = "textfield_character_counter",
 *   label = @Translation("Textfield (character counter)"),
 *   field_types = {
 *     "string"
 *   }
 * )
 */
class TextfieldCharacterCounterWidget extends StringTextfieldWidget {

  use CharacterCounterFieldWidgetTrait;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'counter_step' => 0,
      'counter_total' => 55,
    ] + parent::defaultSettings();
  }

}
