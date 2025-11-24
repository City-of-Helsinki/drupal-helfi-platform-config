<?php

/**
 * @file
 * Contains deploy hooks for the helfi_search module.
 */

declare(strict_types=1);

/**
 * Update the embeddings index datasources and enable multisite option.
 */
function helfi_search_deploy_embeddings_index_datasources_and_multisite() {
  $config = \Drupal::configFactory()->getEditable('search_api.index.embeddings');

  // Do not run the update if the config didn't exist yet.
  if ($config->isNew()) {
    return;
  }

  // Change all uuid-datasources to regular entity datasources.
  $datasource_settings = $config->get('datasource_settings');
  foreach ($datasource_settings as $datasource_id => $datasource_setting_value) {
    if (str_starts_with($datasource_id, 'uuid_entity:')) {
      $new_datasource_id = str_replace('uuid_entity:', 'entity:', $datasource_id);
      $datasource_settings[$new_datasource_id] = $datasource_setting_value;
      unset($datasource_settings[$datasource_id]);
    }
  }
  $config->set('datasource_settings', $datasource_settings);

  // Enable multisite option.
  $options = $config->get('options');
  $options['helfi_platform_config_multisite'] = TRUE;
  $config->set('options', $options);

  $config->save();
}
