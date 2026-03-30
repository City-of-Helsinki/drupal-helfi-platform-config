<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\search_api\processor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileInterface;
use Drupal\helfi_platform_config\Plugin\search_api\processor\Property\MainImageProperty;
use Drupal\image\Entity\ImageStyle;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * A search-api processor for main image field.
 */
#[SearchApiProcessor(
  id: 'main_image_url',
  label: new TranslatableMarkup('Main image'),
  description: new TranslatableMarkup('Indexes main image uri in correct image style'),
  stages: [
    'add_properties' => 0,
  ],
)]
final class MainImageUrl extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL): array {
    $properties = [];

    if ($datasource) {
      $properties['main_image'] = new MainImageProperty([
        'label' => $this->t('Main image: styles'),
        'description' => $this->t('Main image: an array of image styles'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ]);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $dataSourceId = $item->getDataSourceId();

    if ($dataSourceId !== 'entity:node' || !$node = $item->getOriginalObject()->getValue()) {
      return;
    }
    assert($node instanceof NodeInterface);

    return;
    if (!$image = $node->get($properties->entityField)?->entity) {
      return;
    }
    assert($image instanceof MediaInterface);

    if (!$image->hasField('field_media_image') || !$file = $image->get('field_media_image')->entity) {
      return;
    }
    assert($file instanceof FileInterface);

    $this->processFields($item, $file);
  }

  /**
   * Processes the image style field.
   *
   * @param \Drupal\search_api\Item\ItemInterface $item
   *   The item to process.
   * @param \Drupal\file\FileInterface $file
   *   The file to process.
   */
  protected function processFields(
    ItemInterface $item,
    FileInterface $file
  ): void {
    $imageStyles = [
      '1.5_304w_203h' => '1248',
      '1.5_294w_196h' => '992',
      '1.5_220w_147h' => '768',
      '1.5_176w_118h' => '576',
      '1.5_511w_341h' => '320',
      '1.5_608w_406w_LQ' => '1248_2x',
      '1.5_588w_392h_LQ' => '992_2x',
      '1.5_440w_294h_LQ' => '768_2x',
      '1.5_352w_236h_LQ' => '576_2x',
      '1.5_1022w_682h_LQ' => '320_2x',
    ];

    $urls = [];
    foreach ($imageStyles as $styleName => $breakpoint) {
      if ($imageStyle = ImageStyle::load($styleName)) {
        $urls[$breakpoint] = $imageStyle->buildUrl($file->getFileUri());
      }
    }
    $fields = $this
      ->getFieldsHelper()
      ->filterForPropertyPath(
        $item->getFields(),
        'entity:node',
        'main_image_url,'
      );

    foreach ($fields as $field) {
      $field->addValue(json_encode($urls));
    }
  }

}

