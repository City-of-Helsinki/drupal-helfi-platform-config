<?php

declare(strict_types = 1);

namespace Drupal\helfi_react_search\Plugin\search_api\processor;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Plugin\PluginFormTrait;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Converts media reference fields to objects in index.
 *
 * @SearchApiProcessor(
 *   id = "media_reference_to_object",
 *   label = @Translation("Convert media reference to object"),
 *   description = @Translation("Converts media reference fields to objects in index"),
 *   stages = {
 *     "add_properties" = 0,
 *   }
 * )
 */
class MediaReferenceToObject extends ProcessorPluginBase implements PluginFormInterface {

  use PluginFormTrait;

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DataSourceInterface $datasource = NULL): array {
    $properties = [];

    if (!$datasource) {
      $definition = [
        'label' => $this->t('Media objects'),
        'description' => $this->t('Media entities as objects. Define desired media reference fields from processor settings.'),
        'type' => 'object',
        'processor_id' => $this->getPluginId(),
      ];

      $properties['media_as_objects'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $formState): array {
    $form['#description'] = $this->t('Select fields to apply the filter to.');

    $datasources = $this->index->getDatasources();
    $fieldDefs = [];
    $entityFieldManager = \Drupal::service('entity_field.manager');

    foreach ($datasources as $datasource) {
      if (!$entityTypeId = $datasource->getEntityTypeId()) {
        continue;
      }
      $bundles = $datasource->getBundles();

      $fieldDefs = array_map(function ($bundle) use ($entityFieldManager, $entityTypeId) {
        return $entityFieldManager->getFieldDefinitions($entityTypeId, $bundle);
      }, array_keys($bundles));
    }

    foreach (array_merge(...$fieldDefs) as $def) {
      if (!$def instanceof BaseFieldDefinition || $def->getType() !== 'entity_reference') {
        continue;
      }

      $targetDef = $def->getPropertyDefinition('entity')->getTargetDefinition()->getEntityTypeId();

      if ($targetDef !== 'media') {
        continue;
      }

      $enabled = !empty($this->configuration['fields'][$def->getName()]);
      $form['fields'][$def->getName()] = [
        '#type' => 'checkbox',
        '#title' => $def->getLabel()->render(),
        '#default_value' => $enabled,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item): void {
    $fields = $this->getEnabledFields();
    $object = $item->getOriginalObject()->getValue();

    foreach ($fields as $key => $field) {
      $media = $object->get($key)->entity;

      if (
        !$media ||
        !($image = $media->get('field_media_image')) ||
        !($file = $image->entity)
      ) {
        continue;
      }

      $imageStyle = ImageStyle::load('3_2_l');
      $imagePath = $file->getFileUri();
      $imageUri = $imageStyle->buildUri($imagePath);

      if (!file_exists($imageUri)) {
        $imageStyle->createDerivative($imagePath, $imageUri);
      }

      $url = $imageStyle->buildUrl($imagePath);

      $values = [
        'alt' => $image->alt,
        'photographer' => $media->get('field_photographer')->value,
        'title' => $image->title,
        'url' => $url,
      ];

      $itemFields = $item->getFields();
      $itemFields = $this->getFieldsHelper()
        ->filterForPropertyPath($itemFields, NULL, 'media_as_objects');
      foreach ($itemFields as $itemField) {
        $itemField->addValue([$key => $values]);
      }
    }
  }

  /**
   * Return enabled fields.
   */
  protected function getEnabledFields() {
    return array_filter($this->configuration['fields']);
  }

}
