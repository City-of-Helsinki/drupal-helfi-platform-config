<?php

declare(strict_types=1);

namespace Drupal\helfi_react_search\Plugin\search_api\processor;

use Drupal\node\NodeInterface;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Get start and end date for daterange field.
 *
 * @SearchApiProcessor(
 *    id = "project_plan_schedule",
 *    label = @Translation("Project plan schedule"),
 *    description = @Translation("Get start and end date for daterange field"),
 *    stages = {
 *      "add_properties" = 0,
 *    },
 *    locked = true,
 *    hidden = true,
 * )
 */
class ProjectPlanSchedule extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL) {
    $properties = [];

    if ($datasource) {
      $definition = [
        'label' => $this->t('Project plan schedule'),
        'description' => $this->t('Get start and end date for daterange field'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['project_plan_schedule'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $datasourceId = $item->getDataSourceId();

    if ($datasourceId !== 'entity:node') {
      return;
    }

    $node = $item->getOriginalObject()->getValue();

    if ($node instanceof NodeInterface && $node->getType() !== 'project') {
      return;
    }

    if ($node->get('field_project_plan_schedule')->isEmpty()) {
      return;
    }

    $field_project_plan_schedule = $node->get('field_project_plan_schedule')->first()->getValue();

    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), 'entity:node', 'project_plan_schedule');

    if (!isset($fields['project_plan_schedule'])) {
      return;
    }

    if (isset($field_project_plan_schedule['value'])) {
      $fields['project_plan_schedule']->addValue($field_project_plan_schedule['value']);
    }

    if (isset($field_project_plan_schedule['end_value'])) {
      $fields['project_plan_schedule']->addValue($field_project_plan_schedule['end_value']);
    }
  }

}
