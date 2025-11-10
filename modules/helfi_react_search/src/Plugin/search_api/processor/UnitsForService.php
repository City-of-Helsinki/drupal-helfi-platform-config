<?php

declare(strict_types=1);

namespace Drupal\helfi_react_search\Plugin\search_api\processor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;
use Drupal\helfi_react_search\SupportsServiceIndexTrait;
use Drupal\helfi_tpr\Entity\Unit;
use Drupal\helfi_tpr\Entity\Service;
use Drupal\image\Entity\ImageStyle;
use Drupal\media\MediaInterface;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds units for a TPR service to the index.
 *
 * @SearchApiProcessor(
 *   id = "units_for_service",
 *   label = @Translation("Units for service"),
 *   description = @Translation("Adds units for a TPR service to the index"),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
class UnitsForService extends ProcessorPluginBase {

  use SupportsServiceIndexTrait;

  const IMAGE_STYLES = [
    '1.5_1022w_682h_LQ',
  ];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DataSourceInterface $datasource = NULL): array {
    $properties = [];

    if (!$datasource) {
      $definition = [
        'label' => $this->t('Units for service'),
        'description' => $this->t('Indexes units for a TPR service to the index'),
        'type' => 'object',
        'processor_id' => $this->getPluginId(),
        // Custom property that allows configuring subfield properties for
        // object-type (nested) fields. Theses properties should use the
        // Elasticsearch type definitions directly as there's no mapping from
        // the Search API field type to the Elasticsearch type for these.
        // @see SearchApiSubscriber::alterFieldMapping()
        'nested_properties' => [
          'name' => ['type' => 'text'],
          'name_override' => ['type' => 'text'],
          'address' => [
            'properties' => [
              // Only properties defined here will be indexed from the address
              // field.
              // @see self::getAddressValue().
              'address_line1' => ['type' => 'text'],
              'address_line2' => ['type' => 'text'],
              'postal_code' => ['type' => 'keyword'],
              'locality' => ['type' => 'text'],
              'country_code' => ['type' => 'keyword'],
            ],
          ],
          'location' => ['type' => 'geo_point'],
          'image' => [
            'properties' => [
              'variants' => ['type' => 'object'],
              'alt' => ['type' => 'text'],
              'photographer' => ['type' => 'text'],
              'title' => ['type' => 'text'],
              'url' => ['type' => 'keyword'],
            ],
          ],
        ],
      ];

      $properties['units_for_service'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item): void {
    $object = $item->getOriginalObject()->getValue();
    assert($object instanceof Service);
    $language = $item->getLanguage();
    $indexableValue = [];

    $units = $this->entityTypeManager->getStorage('tpr_unit')->getQuery()
      ->accessCheck(FALSE)
      ->condition('services', $object->id())
      ->condition('content_translation_status', 1)
      ->condition('langcode', $language)
      ->execute();

    foreach ($units as $unit_id) {
      $unit = $this->entityTypeManager->getStorage('tpr_unit')->load($unit_id);
      assert($unit instanceof Unit);
      $translation = $unit->getTranslation($language);

      $indexableValue[] = [
        'name' => $translation->get('name')->value,
        'name_override' => $translation->get('name_override')->value,
        'address' => $this->getAddressValue($translation),
        'location' => [
          'lat' => $translation->get('latitude')->value,
          'lon' => $translation->get('longitude')->value,
        ],
        'image' => $this->getImageValue($translation),
      ];
    }

    if (empty($indexableValue)) {
      return;
    }

    $itemFields = $item->getFields();
    $itemFields = $this->getFieldsHelper()
      ->filterForPropertyPath($itemFields, NULL, 'units_for_service');
    foreach ($itemFields as $itemField) {
      $itemField->addValue(array_values($indexableValue));
    }
  }

  /**
   * Get address value from unit entity.
   *
   * @param \Drupal\helfi_tpr\Entity\Unit $unit
   *   The unit translation.
   *
   * @return array
   *   The address value.
   */
  private function getAddressValue(Unit $unit): array {
    $definition = $this->getPropertyDefinitions();
    $address_definition = $definition['units_for_service']['nested_properties']['address']['properties'];
    $field_value = current($unit->get('address')->getValue());

    // Only return properties that are defined in the address definition.
    return array_intersect_key($field_value, $address_definition);
  }

  /**
   * Get image value from unit entity.
   *
   * @param \Drupal\helfi_tpr_config\Entity\Unit $unit
   *   The unit translation.
   *
   * @return array
   *   The image value.
   */
  private function getImageValue(Unit $unit): array {
    $imagePath = $unit->getPictureUri();
    if (!$imagePath) {
      return [];
    }

    $variants = [];
    foreach (self::IMAGE_STYLES as $styleName) {
      $imageStyle = ImageStyle::load($styleName);
      $imageUri = $imageStyle->buildUri($imagePath);

      if (!file_exists($imageUri)) {
        $imageStyle->createDerivative($imagePath, $imageUri);
      }

      $url = $imageStyle->buildUrl($imagePath);
      $variants[$styleName] = $url;
    }

    $media = $unit->get('picture_url_override')->entity;
    $imageFieldItemList = $media instanceof MediaInterface ? $media->get('field_media_image') : NULL;
    $imageValue = $imageFieldItemList instanceof FileFieldItemList ? $imageFieldItemList->getValue() : NULL;

    return [
      'variants' => $variants,
      'alt' => $imageValue[0]['alt'] ?? NULL,
      'photographer' => $media instanceof MediaInterface ? $media->get('field_photographer')->value : NULL,
      'title' => $imageValue[0]['title'] ?? NULL,
      'url' => array_first($variants),
    ];
  }

}
