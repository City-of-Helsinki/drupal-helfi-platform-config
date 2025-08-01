<?php

declare(strict_types=1);

namespace Drupal\helfi_react_search\Plugin\search_api\processor;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\Plugin\DataType\EntityReference;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Plugin\PluginFormTrait;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  private EntityFieldManagerInterface $entityFieldManager;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityFieldManager = $container->get('entity_field.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DataSourceInterface $datasource = NULL): array {
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
    $entityFieldManager = $this->entityFieldManager;

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

      $targetDef = $def->getPropertyDefinition('entity');
      assert($targetDef instanceof EntityReference);

      if ($targetDef->getTargetDefinition()->getEntityTypeId() !== 'media') {
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

    // Define an array of image styles to generate.
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

    foreach ($fields as $key => $field) {
      $media = $object->get($key)->entity;

      if (
        !$media ||
        !($image = $media->get('field_media_image')) ||
        !($file = $image->entity)
      ) {
        continue;
      }

      $imagePath = $file->getFileUri();
      $variants = [];

      foreach ($imageStyles as $styleName => $breakpoint) {
        $imageStyle = ImageStyle::load($styleName);
        $imageUri = $imageStyle->buildUri($imagePath);

        if (!file_exists($imageUri)) {
          $imageStyle->createDerivative($imagePath, $imageUri);
        }

        $url = $imageStyle->buildUrl($imagePath);
        $variants[$breakpoint] = $url;
      }

      $values = [
        'alt' => $image->alt,
        'photographer' => $media->get('field_photographer')->value,
        'title' => $image->title,
        'url' => $variants['1248'],
        'variants' => $variants,
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
