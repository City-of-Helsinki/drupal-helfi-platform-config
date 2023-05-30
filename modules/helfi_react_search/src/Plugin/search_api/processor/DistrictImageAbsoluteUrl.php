<?php

namespace Drupal\helfi_react_search\Plugin\search_api\processor;

use Drupal\node\NodeInterface;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\image\Entity\ImageStyle;

/**
 * Get start and end date for daterange field.
 *
 * @SearchApiProcessor(
 *    id = "district_image_absolute_url",
 *    label = @Translation("Image absolute URL"),
 *    description = @Translation("Generate absolute URL for image"),
 *    stages = {
 *      "add_properties" = 0,
 *    },
 *    locked = true,
 *    hidden = true,
 * )
 */
class DistrictImageAbsoluteUrl extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = [];

    if ($datasource) {
      $definition = [
        'label' => $this->t('District image absolute URL'),
        'description' => $this->t('Generate absolute URL for image'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['district_image_absolute_url'] = new ProcessorProperty($definition);
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

    if ($node instanceof NodeInterface && $node->getType() !== 'district') {
      return;
    }

    if ($node->get('field_district_image')->isEmpty()) {
      return;
    }

    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), 'entity:node', 'district_image_absolute_url');

    if (!isset($fields['district_image_absolute_url'])) {
      return;
    }

    if (!$node->get('field_district_image')->entity) {
      return;
    }

    if ($file = $node->get('field_district_image')->entity->get('field_media_image')->entity) {
      $imageStyle = ImageStyle::load('3_2_s');

      $fields['district_image_absolute_url']->addValue($imageStyle->buildUrl($file->getFileUri()));
    }
  }

}
