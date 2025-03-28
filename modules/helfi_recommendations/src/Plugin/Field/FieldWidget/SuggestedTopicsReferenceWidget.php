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
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_recommendations\Entity\SuggestedTopics;
use Drupal\helfi_recommendations\Entity\SuggestedTopicsInterface;
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
    private readonly EnvironmentResolverInterface $environmentResolver,
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
      $container->get('helfi_api_base.environment_resolver')
    );
  }

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

    $element['published'] = [
      '#type' => 'checkbox',
      '#default_value' => $field->get('published')->getValue() ?? TRUE,
      '#title' => $this->getFieldPropertyDefinition($field, 'published')->getLabel(),
    ];

    $element['show_block'] = [
      '#type' => 'checkbox',
      '#default_value' => $field->get('show_block')->getValue() ?? TRUE,
      '#title' => $this->getFieldPropertyDefinition($field, 'show_block')->getLabel(),
    ];

    $projects = $this->environmentResolver->getProjects();
    $element['instances'] = [
      '#type' => 'checkboxes',
      '#default_value' => $field->get('instances')->getValue() ?? [],
      '#title' => $this->getFieldPropertyDefinition($field, 'instances')->getLabel(),
      '#options' => array_map(fn (Project $project) => $project->label(), $projects),
      '#description' => $this->t('Select the instances that should be used for recommendations. If no instances are selected, recommendations will be shown from all instances.'),
    ];

    $element['content_types'] = [
      '#type' => 'checkboxes',
      '#default_value' => $field->get('content_types')->getValue() ?? [],
      '#title' => $this->getFieldPropertyDefinition($field, 'content_types')->getLabel(),
      // @todo Include TPR entity types & bundles.
      '#options' => [
        'node|news_article' => $this->t('News article'),
        'node|news_item' => $this->t('News item'),
        'node|landing_page' => $this->t('Landing page'),
        'node|page' => $this->t('Standard page'),
      ],
      '#description' => $this->t('Select the content types that should be used for recommendations. If no content types are selected, recommendations will be shown from all content types.'),
    ];

    // Render suggested topics entity.
    // @todo this should use entity view builder.
    if ($hasTargetEntity) {
      $keywords = [];
      foreach ($entity->referencedEntities() as $keyword) {
        $keywords[] = $keyword->label();
      }

      $element['keywords'] = [
        '#type' => 'textarea',
        '#default_value' => implode("\n", $keywords),
        '#title' => $this->t('Generated keywords'),
        '#disabled' => TRUE,
        '#description' => $this->t('Keywords are generated automatically.'),
        '#placeholder' => $this->t('No keywords generated yet.'),
      ];
    }

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
