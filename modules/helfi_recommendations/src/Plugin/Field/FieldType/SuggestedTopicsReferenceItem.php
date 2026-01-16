<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\MapDataDefinition;
use Drupal\helfi_recommendations\Entity\SuggestedTopicsInterface;
use Drupal\helfi_recommendations\TypedData\ComputedReferencePublishedStatus;

/**
 * Defines the 'suggested_topics_reference' field type.
 *
 * @property bool|null $published
 *   Computed published property.
 */
#[FieldType(
  id: "suggested_topics_reference",
  label: new TranslatableMarkup("Recommendation topics"),
  description: new TranslatableMarkup("This field stores the ID and settings related to suggested topics."),
  category: "reference",
  default_widget: "suggested_topics_reference",
  default_formatter: "entity_reference_label",
  list_class: EntityReferenceFieldItemList::class,
)]
class SuggestedTopicsReferenceItem extends EntityReferenceItem {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings(): array {
    return ['target_type' => 'suggested_topics'] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data): array {
    $elements = parent::storageSettingsForm($form, $form_state, $has_data);
    $elements['target_type']['#access'] = FALSE;

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::fieldSettingsForm($form, $form_state);
    $form['handler']['#access'] = FALSE;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = parent::schema($field_definition);
    $schema['columns']['show_block'] = [
      'type' => 'int',
      'size' => 'tiny',
      'default' => 0,
    ];
    $schema['columns']['instances'] = [
      'description' => 'Serialized array of instances to show recommendations from.',
      'type' => 'blob',
      'size' => 'big',
      'serialize' => TRUE,
    ];
    $schema['columns']['content_types'] = [
      'description' => 'Serialized array of content types to show recommendations from.',
      'type' => 'blob',
      'size' => 'big',
      'serialize' => TRUE,
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    $properties = parent::propertyDefinitions($field_definition);

    $properties['published'] = DataDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Show this page in recommendations for other pages', options:['context' => 'helfi_recommendations']))
      ->setClass(ComputedReferencePublishedStatus::class)
      ->setComputed(TRUE)
      ->setRequired(FALSE);

    $properties['show_block'] = DataDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Show automatically recommended content on this page', options:['context' => 'helfi_recommendations']))
      ->setRequired(FALSE);

    $properties['instances'] = MapDataDefinition::create()
      ->setLabel(new TranslatableMarkup('Instances to show recommendations from', options: ['context' => 'helfi_recommendations']))
      ->setRequired(FALSE);

    $properties['content_types'] = MapDataDefinition::create()
      ->setLabel(new TranslatableMarkup('Allowed content types for recommendations', options: ['context' => 'helfi_recommendations']))
      ->setRequired(FALSE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function delete(): void {
    $this->entity?->delete();
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(): void {
    parent::preSave();

    // The 'published'-property in this field is computed and synced from/to
    // the 'status'-property of the referenced 'suggested topics'-entity.
    // Changing that field value will change the published status of the
    // referenced entity, so the entity needs to be saved at the end.
    // The above parent::preSave() will take care of this when the entity is
    // new.
    //
    // @see \Drupal\helfi_recommendations\TypedData\ComputedReferencePublishedStatus::getValue().
    // @see \Drupal\helfi_recommendations\TypedData\ComputedReferencePublishedStatus::setValue().
    if (!$this->entity->isNew()) {
      $this->entity->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave($update) {
    // If the entity is new, set the parent entity data on the target entity.
    if (!$update) {
      $entity = $this->entity;
      assert($entity instanceof SuggestedTopicsInterface);

      $parent = $this->getEntity();
      $entity->setParentEntity($parent);
      $entity->save();
    }

    // No need to rewrite the field item to storage.
    return FALSE;
  }

}
