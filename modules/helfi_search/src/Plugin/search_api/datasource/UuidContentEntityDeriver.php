<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Plugin\search_api\datasource;

use Drupal\Core\Entity\ContentEntityType;
use Drupal\search_api\Plugin\search_api\datasource\ContentEntityDeriver;

/**
 * Derives a datasource plugin content entity type that has uuid field.
 *
 * @see \Drupal\search_api\Plugin\search_api\datasource\ContentEntityDatasource
 */
class UuidContentEntityDeriver extends ContentEntityDeriver {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    if (!isset($this->derivatives)) {
      $plugin_derivatives = [];
      foreach ($this->getEntityTypeManager()->getDefinitions() as $entity_type => $entity_type_definition) {
        // We only support content entity types at the moment, since config
        // entities don't implement \Drupal\Core\TypedData\ComplexDataInterface.
        if ($entity_type_definition instanceof ContentEntityType && $entity_type_definition->hasKey('uuid')) {
          $plugin_derivatives[$entity_type] = [
            'entity_type' => $entity_type,
            'label' => $entity_type_definition->getLabel() . ' (UUID)',
            'description' => $this->t('Provides %entity_type entities for indexing and searching.', ['%entity_type' => $entity_type_definition->getLabel()]),
          ] + $base_plugin_definition;
        }
      }

      $this->derivatives = $plugin_derivatives;
    }

    return $this->derivatives;
  }

}
