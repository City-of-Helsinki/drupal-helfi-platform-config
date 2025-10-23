<?php

declare(strict_types=1);

namespace Drupal\helfi_react_search\Plugin\search_api\processor;

use Drupal\helfi_react_search\SupportsServiceIndexTrait;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Adds channels for a TPR service to the index.
 *
 * @SearchApiProcessor(
 *   id = "channels_for_service",
 *   label = @Translation("Channels for service"),
 *   description = @Translation("Adds channels for a TPR service to the index"),
 *   stages = {
 *     "add_properties" = 0,
 *   }
 * )
 */
class ChannelsForService extends ProcessorPluginBase {

  use SupportsServiceIndexTrait;

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DataSourceInterface $datasource = NULL): array {
    $properties = [];

    if (!$datasource) {
      $definition = [
        'label' => $this->t('Channels for service'),
        'description' => $this->t('Indexes channels for a TPR service to the index'),
        'type' => 'object',
        'processor_id' => $this->getPluginId(),
      ];

      $properties['channels_for_service'] = new ProcessorProperty($definition);
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

    $errand_services = $object->get('errand_services')->referencedEntities();
    foreach ($errand_services as $errand_service) {
      $channels = $errand_service->get('channels')->referencedEntities();
      foreach ($channels as $channel) {
        $translation = $channel->getTranslation($language);
        $type_id = $translation->get('type')->value;
        $type_label = $translation->get('type_string')->value;
        $indexableValue[$type_id] = [
          'id' => $type_id,
          'label' => $type_label,
        ];
      }
    }

    if (empty($indexableValue)) {
      return;
    }

    $itemFields = $item->getFields();
    $itemFields = $this->getFieldsHelper()
      ->filterForPropertyPath($itemFields, NULL, 'channels_for_service');
    foreach ($itemFields as $itemField) {
      $itemField->addValue(array_values($indexableValue));
    }
  }

}
