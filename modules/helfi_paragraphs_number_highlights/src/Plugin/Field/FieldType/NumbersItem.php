<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_number_highlights\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'numbers_item' field type.
 *
 * @property string $number
 * @property string $text
 */
#[FieldType(
  id: "numbers_item",
  label: new TranslatableMarkup("Numbers Item", [], ['context' => 'Number highlights']),
  description: new TranslatableMarkup("Stores a short number and text pair.", [], ['context' => 'Number highlights']),
  default_widget: "numbers_item_widget",
)]
class NumbersItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['number'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Number', [], ['context' => 'Number highlights']))
      ->setRequired(TRUE);

    $properties['text'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Text', [], ['context' => 'Number highlights']))
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'number' => [
          'description' => 'A string representing a short number.',
          'type' => 'varchar',
          'length' => 7,
        ],
        'text' => [
          'description' => 'A short description or label.',
          'type' => 'varchar',
          'length' => 60,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $number = $this->get('number')->getValue();
    $text = $this->get('text')->getValue();
    return ($number === NULL || $number === '') && ($text === NULL || $text === '');
  }

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    return 'number';
  }

}
