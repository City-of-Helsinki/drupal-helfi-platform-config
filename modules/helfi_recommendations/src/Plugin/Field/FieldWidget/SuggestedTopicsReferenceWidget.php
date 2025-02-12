<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_recommendations\Entity\SuggestedTopics;
use Drupal\helfi_recommendations\Entity\SuggestedTopicsInterface;

/**
 * Defines the 'suggested_topics_reference' field widget.
 */
#[FieldWidget(
  id: 'suggested_topics_reference',
  label: new TranslatableMarkup('Suggested topics'),
  description: new TranslatableMarkup('Allows configuring recommendations for the entity'),
  field_types: ['suggested_topics_reference'],
)]
final class SuggestedTopicsReferenceWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    assert($items instanceof EntityReferenceFieldItemListInterface);

    $hasTargetEntity = !$items[$delta]->isEmpty();
    $entity = $hasTargetEntity ? $items[$delta]->entity : SuggestedTopics::create();
    assert($entity instanceof SuggestedTopicsInterface);

    // Set the entity this field belongs to as the parent entity.
    $entity->setParentEntity($items->getEntity());

    $element['entity'] = [
      '#type' => 'value',
      '#value' => $entity,
    ];

    // Add custom configuration fields.
    $field = $items[$delta];
    $definition = $field
      ->getFieldDefinition()
      ->getFieldStorageDefinition()
      ->getPropertyDefinition('published');

    $element['published'] = [
      '#type' => 'checkbox',
      '#default_value' => $field->get('published')->getValue() ?? TRUE,
      '#title' => $definition->getLabel(),
    ];

    // Render suggested topics entity.
    // @todo this should use entity view builder.
    if ($hasTargetEntity) {
      $keywords = [];
      foreach ($entity->referencedEntities() as $keyword) {
        $keywords[] = $keyword->label();
      }

      $element['keywords'] = [
        '#theme' => 'item_list',
        '#items' => $keywords,
      ];
    }

    return $element;
  }

}
