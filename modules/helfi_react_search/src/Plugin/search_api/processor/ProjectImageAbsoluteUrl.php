<?php

namespace Drupal\helfi_react_search\Plugin\search_api\processor;

use Drupal\image\Entity\ImageStyle;
use Drupal\node\NodeInterface;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Get start and end date for daterange field.
 *
 * @SearchApiProcessor(
 *    id = "project_image_absolute_url",
 *    label = @Translation("Image absolute URL"),
 *    description = @Translation("Generate absolute URL for image"),
 *    stages = {
 *      "add_properties" = 0,
 *    },
 *    locked = true,
 *    hidden = true,
 * )
 */
class ProjectImageAbsoluteUrl extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = [];

    if ($datasource) {
      $definition = [
        'label' => $this->t('Project image absolute URL'),
        'description' => $this->t('Generate absolute URL for image'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['project_image_absolute_url'] = new ProcessorProperty($definition);
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

    if ($node->get('field_project_image')->isEmpty()) {
      return;
    }

    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), 'entity:node', 'project_image_absolute_url');

    if (!isset($fields['project_image_absolute_url'])) {
      return;
    }

    if (!$node->get('field_project_image')->entity) {
      return;
    }

    if ($file = $node->get('field_project_image')->entity->get('field_media_image')->entity) {
      $imageStyle = ImageStyle::load('3_2_s');

      $fields['project_image_absolute_url']->addValue($imageStyle->buildUrl($file->getFileUri()));
    }
  }

}
