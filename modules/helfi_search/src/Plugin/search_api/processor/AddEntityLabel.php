<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Plugin\search_api\processor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Adds the entity's label to the indexed data.
 */
#[SearchApiProcessor(
  id: 'helfi_entity_label',
  label: new TranslatableMarkup('Entity label'),
  description: new TranslatableMarkup("Adds the entity's label to the indexed data."),
  stages: [
    'add_properties' => 0,
  ],
)]
final class AddEntityLabel extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL): array {
    $properties = [];

    if (!$datasource) {
      $properties['helfi_entity_label'] = new ProcessorProperty([
        'label' => $this->t('Entity label'),
        'description' => $this->t("The entity's label."),
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
    $entity = $item->getOriginalObject()->getValue();
    $label = $entity->label();

    if ($label === NULL) {
      return;
    }

    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(FALSE), NULL, 'helfi_entity_label');

    foreach ($fields as $field) {
      $field->addValue($label);
    }
  }

}
