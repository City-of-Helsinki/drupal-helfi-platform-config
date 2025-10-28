<?php

declare(strict_types=1);

namespace Drupal\helfi_react_search\Plugin\search_api\processor;

use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\helfi_react_search\SupportsServiceIndexTrait;
use Drupal\helfi_tpr\Entity\Channel;
use Drupal\helfi_tpr\Entity\ErrandService;
use Drupal\helfi_tpr\Entity\Service;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\IndexInterface;
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
 *   },
 *   locked = true,
 *   hidden = true,
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
        'nested_properties' => [
          'id' => [
            'type' => 'keyword',
          ],
          'label' => [
            'type' => 'text',
          ],
        ],
        'processor_id' => $this->getPluginId(),
      ];

      $properties['channels_for_service'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   *
   * The relation path to channels is service -> errand_services -> channels.
   */
  public function addFieldValues(ItemInterface $item): void {
    $object = $item->getOriginalObject()->getValue();
    assert($object instanceof Service);
    $language = $item->getLanguage();
    $indexableValue = [];

    // Get all errand services for the service.
    $errand_services_field = $object->get('errand_services');
    assert($errand_services_field instanceof EntityReferenceFieldItemListInterface);
    $errand_services = $errand_services_field->referencedEntities();

    foreach ($errand_services as $errand_service) {
      assert($errand_service instanceof ErrandService);

      // Get all channels for the errand service.
      $channels_field = $errand_service->get('channels');
      assert($channels_field instanceof EntityReferenceFieldItemListInterface);
      $channels = $channels_field->referencedEntities();

      foreach ($channels as $channel) {
        assert($channel instanceof Channel);

        $translation = $channel->getTranslation($language);
        $type_id = $translation->get('type')->value;
        $type_label = $translation->get('type_string')->value;

        // Add channel type and label to the index.
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
