<?php

declare(strict_types=1);

namespace Drupal\helfi_react_search\Plugin\search_api\processor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\helfi_react_search\SupportsServiceIndexTrait;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\tpr\ContentTranslationStatus;
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
 *   }
 * )
 */
class UnitsForService extends ProcessorPluginBase {

  use SupportsServiceIndexTrait;

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
    $language = $item->getLanguage();
    $indexableValue = [];

    $units = $this->entityTypeManager->getStorage('tpr_unit')->getQuery()
      ->accessCheck(FALSE)
      ->condition('services', $object->id())
      ->condition('content_translation_status', 1)
      ->condition('langcode', $language)
      ->execute();

    foreach ($units as $unit_id) {
      /** @var \Drupal\tpr\Entity\Unit $unit */
      $unit = $this->entityTypeManager->getStorage('tpr_unit')->load($unit_id);
      $translation = $unit->getTranslation($language);

      $indexableValue[] = [
        'name' => $translation->label(),
        'name_override' => $translation->get('name_override')->value,
        'address' => $translation->get('address')->getValue()[0],
        'latitude' => $translation->get('latitude')->value,
        'longitude' => $translation->get('longitude')->value,
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

}
