<?php

declare(strict_types = 1);

namespace Drupal\helfi_react_search\Plugin\search_api\processor;

use Drupal\helfi_react_search\SupportsUnitIndexTrait;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Checks if given TPR entity is a school.
 *
 * @SearchApiProcessor(
 *   id = "coordinates",
 *   label = @Translation("Add coordinates"),
 *   description = @Translation("Adds coordinates to the index based on selections"),
 *   stages = {
 *     "add_properties" = 0,
 *   }
 * )
 */
class Coordinates extends ProcessorPluginBase {

  use SupportsUnitIndexTrait;

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DataSourceInterface $datasource = NULL): array {
    $properties = [];

    if (!$datasource) {
      $definition = [
        'label' => $this->t('Coordinates'),
        'description' => $this->t('Indexes coordinates of tpr_unit entity as geo_point field to elastic.'),
        'type' => 'object',
        'processor_id' => $this->getPluginId(),
      ];

      $properties['coordinates'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item): void {
    var_dump('EEEEEEE');

    $object = $item->getOriginalObject()->getValue();

    $indexableValue = [
      'lat' => $object->get('latitude')->value,
      'lon' => $object->get('longitude')->value,
    ];

    $itemFields = $item->getFields();
    $itemFields = $this->getFieldsHelper()
      ->filterForPropertyPath($itemFields, NULL, 'coordinates');
    foreach ($itemFields as $itemField) {
      $itemField->addValue($indexableValue);
    }
  }

}
