<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_recommendations\Entity\SuggestedTopics;
use Drupal\helfi_recommendations\Entity\SuggestedTopicsInterface;
use Drupal\helfi_recommendations\RecommendationManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    private readonly RecommendationManagerInterface $recommendationManager,
    private readonly StateInterface $state,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get(RecommendationManagerInterface::class),
      $container->get('state'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    assert($items instanceof EntityReferenceFieldItemListInterface);

    $hasTargetEntity = !$items[$delta]->isEmpty();
    $entity = $hasTargetEntity && $items[$delta]->entity ? $items[$delta]->entity : SuggestedTopics::create();
    assert($entity instanceof SuggestedTopicsInterface);

    // Set the entity this field belongs to as the parent entity.
    $entity->setParentEntity($items->getEntity());

    $element['entity'] = [
      '#type' => 'value',
      '#value' => $entity,
    ];

    // Add custom configuration fields.
    $field = $items[$delta];

    $element['published'] = [
      '#type' => 'checkbox',
      '#default_value' => $field->get('published')->getValue() ?? TRUE,
      '#title' => $this->getFieldPropertyDefinition($field, 'published')->getLabel(),
    ];

    $default_show_block = $this->state->get('helfi_recommendations.suggested_topics_default_show_block', TRUE);
    $element['show_block'] = [
      '#type' => 'checkbox',
      '#default_value' => $field->get('show_block')->getValue() ?? $default_show_block,
      '#title' => $this->getFieldPropertyDefinition($field, 'show_block')->getLabel(),
    ];

    $element['instances'] = [
      '#type' => 'checkboxes',
      '#default_value' => $field->get('instances')->getValue() ?? [],
      '#title' => $this->getFieldPropertyDefinition($field, 'instances')->getLabel(),
      '#options' => $this->recommendationManager->getAllowedInstances(),
      '#description' => $this->t('Select the instances that should be used for recommendations. If no instances are selected, recommendations will be shown from all instances.', options: ['context' => 'helfi_recommendations']),
    ];

    $element['content_types'] = [
      '#type' => 'checkboxes',
      '#default_value' => $field->get('content_types')->getValue() ?? [],
      '#title' => $this->getFieldPropertyDefinition($field, 'content_types')->getLabel(),
      '#options' => $this->recommendationManager->getAllowedContentTypesAndBundles(),
      '#description' => $this->t('Select the content types that should be used for recommendations. If no content types are selected, recommendations will be shown from all content types.', options: ['context' => 'helfi_recommendations']),
    ];

    // Generated keywords.
    $keywords = [];
    if ($hasTargetEntity) {
      foreach ($entity->getKeywords() as $keyword) {
        $keywords[] = sprintf('%s (%s)', $keyword['label'], number_format((float) $keyword['score'], 2));
      }
    }
    $element['keywords'] = [
      '#type' => 'textarea',
      '#default_value' => implode("\n", $keywords),
      '#title' => $this->t('Generated keywords', options: ['context' => 'helfi_recommendations']),
      '#disabled' => TRUE,
      '#description' => $this->t('Keywords are generated automatically. Trailing number is a score between 0 and 1, and indicates the relevance of the keyword.', options: ['context' => 'helfi_recommendations']),
      '#placeholder' => $this->t('No keywords generated yet.', options: ['context' => 'helfi_recommendations']),
    ];

    return $element;
  }

  /**
   * Get the property definition for a field.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $field
   *   The field item.
   * @param string $property_name
   *   The property name.
   *
   * @return \Drupal\Core\TypedData\DataDefinitionInterface
   *   The property definition.
   */
  private function getFieldPropertyDefinition(FieldItemInterface $field, string $property_name) {
    return $field
      ->getFieldDefinition()
      ->getFieldStorageDefinition()
      ->getPropertyDefinition($property_name);
  }

}
