<?php

/**
 * @file
 * Post update functions for HDBT Admin editorial module.
 */

use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Install 'hide_sidebar_navigation' field to nodes and TPR entities.
 */
function hdbt_admin_editorial_post_update_install_hide_sidebar_navigation_field(&$sandbox = NULL) {

  $field = 'hide_sidebar_navigation';
  $entity_types = [
    'tpr_unit' => 'helfi_tpr',
    'tpr_service' => 'helfi_tpr',
    'node' => 'node',
  ];

  // Install hide_sidebar_navigation field to chosen entities.
  foreach ($entity_types as $entity_type => $module) {
    if ($module === 'helfi_tpr' && !\Drupal::moduleHandler()->moduleExists('helfi_tpr')) {
      continue;
    }

    $entity_definition_update_manager = \Drupal::entityDefinitionUpdateManager();
    $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions($entity_type, $entity_type);

    // Install hide sidebar navigation field, if hide sidebar navigation field
    // has not been installed to current entity.
    if (
      !empty($field_definitions[$field]) &&
      $field_definitions[$field] instanceof FieldStorageDefinitionInterface &&
      empty($entity_definition_update_manager->getFieldStorageDefinition($field, $entity_type))
    ) {
      $entity_definition_update_manager->installFieldStorageDefinition($field, $entity_type, 'hdbt_admin_editorial', $field_definitions[$field]);
    }
  }
}
