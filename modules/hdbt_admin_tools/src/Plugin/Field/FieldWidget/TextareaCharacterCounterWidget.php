<?php

declare(strict_types=1);

namespace Drupal\hdbt_admin_tools\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Plugin\Field\FieldWidget\StringTextareaWidget;

/**
 * Plugin implementation of the 'textarea_character_counter' widget.
 *
 * @FieldWidget(
 *   id = "textarea_character_counter",
 *   label = @Translation("Text area (character counter)"),
 *   field_types = {
 *     "string_long",
 *   }
 * )
 */
class TextareaCharacterCounterWidget extends StringTextareaWidget {

  use CharacterCounterFieldWidgetTrait;

}
