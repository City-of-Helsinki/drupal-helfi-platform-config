<?php

declare(strict_types=1);

namespace Drupal\helfi_react_search\Plugin\search_api\processor;

use Drupal\image\Entity\ImageStyle;
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

    if ($datasourceId !== 'entity:node' || !$node = $item->getOriginalObject()->getValue()) {
      return;
    }

    $type = $node->getType();

    if ($type !== 'project') {
      return;
    }

    $image = $node->get('field_project_image')->entity;

    if (!$image || !$image->hasField('field_media_image') || !$file = $image->get('field_media_image')->entity) {
      return;
    }

    $imagePath = $file->getFileUri();
    $imageStyles = [
      '1.5_378w_252h' => '1248',
      '1.5_341w_227h' => '992',
      '1.5_264w_176h' => '768',
      '1.5_217w_145h' => '576',
      '1.5_511w_341h' => '320',
      '1.5_756w_504h_LQ' => '1248_2x',
      '1.5_682w_454h_LQ' => '992_2x',
      '1.5_528w_352h_LQ' => '768_2x',
      '1.5_434w_290h_LQ' => '576_2x',
      '1.5_1022w_682h_LQ' => '320_2x',
    ];

    $urls = [];
    foreach ($imageStyles as $styleName => $breakpoint) {
      $imageStyle = ImageStyle::load($styleName);
      if ($imageStyle) {
        $urls[$breakpoint] = $imageStyle->buildUrl($imagePath);
      }
    }

    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), 'entity:node', 'project_image_absolute_url');
    foreach ($fields as $field) {
      $field->addValue(json_encode($urls));
    }
  }

}
