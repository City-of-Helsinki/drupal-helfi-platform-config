<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines the 'scored_entity_reference' field type.
 *
 * @property mixed $score Item score.
 *
 * @see \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem
 * @see \Drupal\Core\Field\Plugin\Field\FieldType\FloatItem
 */
#[FieldType(
  id: "scored_entity_reference",
  label: new TranslatableMarkup("Scored entity reference"),
  description: new TranslatableMarkup("An entity field containing a scored entity reference."),
  category: "reference",
  default_widget: "entity_reference_autocomplete",
  default_formatter: "entity_reference_label",
  list_class: EntityReferenceFieldItemList::class,
)]
final class ScoredEntityReferenceItem extends EntityReferenceItem {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    $properties = parent::propertyDefinitions($field_definition);

    $properties['score'] = DataDefinition::create('float')
      ->setLabel(new TranslatableMarkup('Score'))
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {
    $schema = parent::schema($field_definition);

    // Score is a decimal number between 0 and 1.
    $schema['columns']['score'] = [
      'type' => 'float',
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition): array {
    $values = parent::generateSampleValue($field_definition);

    $values['score'] = random_int(0, 100) / 100;

    return $values;
  }

}
