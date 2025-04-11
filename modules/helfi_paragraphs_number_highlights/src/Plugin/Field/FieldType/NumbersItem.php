<?php

namespace Drupal\helfi_paragraphs_number_highlights\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'numbers_item' field type.
 */
#[FieldType(
  id: "numbers_item",
  label: new TranslatableMarkup("Numbers Item"),
  description: new TranslatableMarkup("Stores a short number and text pair."),
  default_widget: "numbers_item_widget",
  default_formatter: "numbers_item_formatter"
)]
class NumbersItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['number'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Number'))
      ->setRequired(TRUE);

    $properties['text'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Text'))
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
          'length' => 6,
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
