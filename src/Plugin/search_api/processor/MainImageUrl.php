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
 * A search-api processor for Main image media field.
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
        'description' => $this->t('Contains the original file properties and image styles for given media image field.'),
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
      ->filterForPropertyPath($item->getFields(), $item->getDatasourceId(), property_path: 'main_image_url');

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
    $configuration = $field->getConfiguration();

    if (!isset($configuration['field_name'], $configuration['image_styles'])) {
      throw new \LogicException('Missing required "field_name" or "image_styles" configuration.');
    }

    if (!$entity->hasField($configuration['field_name']) || !$media = $entity->get($configuration['field_name'])?->entity) {
      return [];
    }
    assert($media instanceof MediaInterface);

    if (!$media->hasField('field_media_image') || !$file = $media->get('field_media_image')?->entity) {
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

    foreach ($configuration['image_styles'] ?? [] as $style) {
      ['id' => $id, 'breakpoint' => $breakpoint] = $style;

      if ($imageStyle = ImageStyle::load($id)) {
        $data['styles'][] = [
          'breakpoint' => $breakpoint,
          'url' => $imageStyle->buildUrl($file->getFileUri()),
        ];
      }
    }
    return $data;
  }

}
