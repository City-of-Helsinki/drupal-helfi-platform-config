<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\search_api\processor;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileInterface;
use Drupal\helfi_platform_config\Plugin\search_api\processor\Property\MainImageProperty;
use Drupal\image\Entity\ImageStyle;
use Drupal\media\MediaInterface;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A search-api processor for main image field.
 */
#[SearchApiProcessor(
  id: 'main_image_url',
  label: new TranslatableMarkup('Main image'),
  description: new TranslatableMarkup('Indexes Main image and image style URLs'),
  stages: [
    'add_properties' => 0,
  ],
)]
final class MainImageUrl extends ProcessorPluginBase {

  /**
   * The file url generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  private FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) : self {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->fileUrlGenerator = $container->get(FileUrlGeneratorInterface::class);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL): array {
    $properties = [];

    if ($datasource) {
      $properties['main_image_url'] = new MainImageProperty([
        'label' => $this->t('Main image: URL'),
        'description' => $this->t('Contains the original file properties and image styles.'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ]);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item): void {
    if (!($entity = $item->getOriginalObject()?->getValue()) || !$entity instanceof ContentEntityInterface) {
      return;
    }
    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), NULL, property_path: 'main_image_url');

    foreach ($fields as $field) {
      if (!$data = $this->getFieldValue($entity, $field)) {
        continue;
      }
      $field->addValue(json_encode($data));
    }

  }

  /**
   * Gets the file data for given field and entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param \Drupal\search_api\Item\FieldInterface $field
   *   The field to process.
   *
   * @return array
   *   The file data.
   */
  private function getFieldValue(ContentEntityInterface $entity, FieldInterface $field): array {
    $fieldName = $field->getConfiguration()['field_name'] ?? NULL;

    if (!$fieldName || !$entity->hasField($fieldName) || !$image = $entity->get($fieldName)?->entity) {
      return [];
    }
    assert($image instanceof MediaInterface);

    if (!$image->hasField('field_media_image') || !$file = $image->get('field_media_image')?->entity) {
      return [];
    }
    assert($file instanceof FileInterface);

    $data = [
      'original' => [
        'url' => $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri()),
        'size' => $file->getSize(),
        'mime' => $file->getMimeType(),
      ],
      'styles' => [],
    ];

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

    foreach ($imageStyles as $styleName => $breakpoint) {
      if ($imageStyle = ImageStyle::load($styleName)) {
        $data['styles'][$breakpoint] = $imageStyle->buildUrl($file->getFileUri());
      }
    }
    return $data;
  }

}
