<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations\Plugin\Field\FieldType;

use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
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
final class SuggestedTopicsReferenceItem extends EntityReferenceItem {

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

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    $properties = parent::propertyDefinitions($field_definition);

    $properties['published'] = DataDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Show this page in recommendations for other pages', [], ['context' => 'annif']))
      ->setClass(ComputedReferencePublishedStatus::class)
      ->setComputed(TRUE)
      ->setRequired(FALSE);

    $properties['show_block'] = DataDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Show automatically recommended content on this page', [], ['context' => 'annif']))
      ->setClass(ComputedReferencePublishedStatus::class)
      ->setComputed(TRUE)
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
    $entity = $this->entity;
    assert($entity instanceof SuggestedTopicsInterface);

    // Overwrite published status if parent entity is not published.
    // Content recommendation should never give unpublished entities
    // as a result.
    $parent = $this->getEntity();
    if ($parent instanceof EntityPublishedInterface && !$parent->isPublished()) {
      $entity->setUnpublished();
    }

    parent::preSave();

    if (!$this->entity->isNew()) {
      $this->entity->save();
    }
  }

}
