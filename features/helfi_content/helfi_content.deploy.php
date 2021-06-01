<?php

/**
 * @file
 * Contains deploy functions for HELfi content.
 */

use Drupal\Core\Config\FileStorage;

/**
 * Install Remote Video paragraph.
 */
function helfi_content_deploy_9001_remote_video_paragraph() {
  if (!\Drupal::state()->get('helfi_content_deploy_9001_remote_video_paragraph')) {
    $config_path = drupal_get_path('module', 'helfi_content') . '/config/install';
    $source = new FileStorage($config_path);

    // Handle base configuration files.
    $configurations = [
      'core.entity_form_display.paragraph.remote_video.default',
      'core.entity_view_display.paragraph.remote_video.default',
      'field.storage.paragraph.field_remote_video',
      'paragraphs.paragraphs_type.remote_video',
      'field.field.node.article.field_content',
      'field.field.node.landing_page.field_content',
      'field.field.node.page.field_content',
      'field.field.paragraph.remote_video.field_remote_video',
    ];

    /** @var \Drupal\Core\Config\StorageInterface $config_storage */
    $config_storage = \Drupal::service('config.storage');

    foreach ($configurations as $configuration) {
      $config_storage->write($configuration, $source->read($configuration));
    }

    // Handle translations.
    $configuration_translations = [
      'fi' => [
        'paragraphs.paragraphs_type.remote_video',
      ],
    ];

    $language_manager = \Drupal::languageManager();
    foreach ($configuration_translations as $language => $translations) {
      $translate_source = new FileStorage($config_path . '/language/' . $language);
      foreach ($translations as $translation) {
        $config = $language_manager->getLanguageConfigOverride($language, $translation);
        $translated_configs = $translate_source->read($translation);
        foreach ($translated_configs as $key => $value) {
          $config->set($key, $value);
        }
        $config->save();
      }
    }

    // Set installed state for this update.
    \Drupal::state()->set('helfi_content_deploy_9001_remote_video_paragraph', TRUE);
    return t('Successfully installed Remote Video paragraph. Please export configurations (conf/cmi) and check for possible customised configuration changes.');
  }
}
