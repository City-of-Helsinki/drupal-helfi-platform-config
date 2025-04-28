<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations\Plugin\search_api\processor;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\helfi_recommendations\Plugin\Field\FieldType\ScoredEntityReferenceItem;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Indexes uuid with langcode.
 *
 * @SearchApiProcessor(
 *   id = "scored_reference",
 *   label = @Translation("Scored references"),
 *   description = @Translation("Indexes scored references"),
 *   stages = {
 *     "add_properties" = 0
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
final class ScoredReferenceProcessor extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DataSourceInterface $datasource = NULL) : array {
    $properties = [];

    if ($datasource) {
      $propertyDefinitions = $datasource->getPropertyDefinitions();
      foreach ($propertyDefinitions as $id => $definition) {
        // Properties for scored entity reference fields.
        if (
          $definition instanceof FieldDefinitionInterface &&
          $definition->getType() === 'scored_entity_reference'
        ) {
          $properties[$id . '_scored'] = new ProcessorProperty([
            'label' => $this->t('Scored reference'),
            'description' => $this->t('Indexes referenced item labels with a score'),
            'type' => 'scored_item',
            'processor_id' => $this->getPluginId(),
          ]);
        }
      }
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(Iteminterface $item) : void {
    $entity = $item->getOriginalObject()?->getValue();

    if (!$entity instanceof ContentEntityInterface) {
      return;
    }

    $objectSupport = $this->index->getServerInstance()->getBackendId() === 'elasticsearch';

    foreach ($item->getFields() as $field) {
      if ($field->getOriginalType() !== 'scored_item') {
        continue;
      }

      $property = substr($field->getPropertyPath(), 0, -strlen("_scored"));

      $scoredReferenceField = $entity->get($property);
      foreach ($scoredReferenceField as $scoredReference) {
        assert($scoredReference instanceof ScoredEntityReferenceItem);

        $value = [
          'score' => (float) $scoredReference->score,
          'label' => $scoredReference->entity->label(),
        ];

        $field->addValue($objectSupport ? $value : json_encode($value));
      }
    }
  }

}
