<?php

declare(strict_types=1);

namespace Drupal\hdbt_admin_tools\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\Plugin\Field\FieldWidget\StringTextareaWidget;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'textarea_character_counter' widget.
 */
#[FieldWidget(
  id: 'textarea_character_counter',
  label: new TranslatableMarkup('Text area (character counter)'),
  field_types: [
    'string_long',
  ],
)]
class TextareaCharacterCounterWidget extends StringTextareaWidget {

  use CharacterCounterFieldWidgetTrait;

}
