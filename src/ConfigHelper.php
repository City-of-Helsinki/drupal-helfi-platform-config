<?php

namespace Drupal\helfi_platform_config;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Symfony\Component\Yaml\Yaml;

/**
 * Functions to handle configurations.
 *
 * @package Drupal\helfi_platform_config
 */
class ConfigHelper {

  /**
   * Install new configuration.
   *
   * @param string $config_location
   *   Absolute path to the configuration to be created.
   * @param string $config_name
   *   Name of the configuration to install.
   */
  public static function installNewConfig(string $config_location, string $config_name): void {
    $config_factory = \Drupal::configFactory();
    $filepath = "{$config_location}{$config_name}.yml";
    if (file_exists($filepath)) {
      $data = Yaml::parse(file_get_contents($filepath));
      if (is_array($data)) {
        $config_factory->getEditable($config_name)->setData($data)->save(TRUE);
      }
    }
  }

  /**
   * Update existing configuration.
   *
   * Update existing single configuration with similar fashion as
   * ConfigBase::merge() would merge the configuration. However merge() uses
   * NestedArray::mergeDeepArray which is called with $preserve_integer_keys
   * flag and that will eventually override sequential array values what
   * shouldn't get overridden.
   * This might manifest as custom configuration values presented as
   * dissociative array being removed from configuration.
   *
   * Instead the customized self::mergeDeepArray is used, which based on the
   * NestedArray method. In self::mergeDeepArray the sequential array values
   * get appended to the resulting array and before returning the results
   * the array_unique is run, resulting to unique sequential arrays.
   *
   * @param string $config_location
   *   Absolute path to the configuration to be updated.
   * @param string $config_name
   *   Name of the configuration to update.
   *
   * @see \Drupal\Core\Config\ConfigBase::merge()
   */
  public static function updateExistingConfig(string $config_location, string $config_name): void {
    $config_factory = \Drupal::configFactory();
    $filepath = "{$config_location}{$config_name}.yml";
    if (file_exists($filepath)) {
      $new_config = Yaml::parse(file_get_contents($filepath));
      if (is_array($new_config)) {
        $original_config = $config_factory->getEditable($config_name);
        $updated_config = self::mergeDeepArray([
          $original_config->getRawData(),
          $new_config,
        ]);
        $original_config->setData($updated_config)->save(TRUE);
      }
    }
  }

  /**
   * Install new configuration translation.
   *
   * @param string $config_location
   *   Absolute path to the configuration to be created.
   * @param string $config_name
   *   Name of the configuration translation to install.
   */
  public static function installNewConfigTranslation(string $config_location, string $config_name): void {
    $language_manager = \Drupal::languageManager();

    foreach ($language_manager->getLanguages() as $language) {
      if ($language->getId() !== $language_manager->getDefaultLanguage()->getId()) {
        $filepath = "{$config_location}{$language->getId()}/{$config_name}.yml";
        if (file_exists($filepath) && $yaml = file_get_contents($filepath)) {
          $data = Yaml::parse($yaml);
          $language_manager->getLanguageConfigOverride($language->getId(), $config_name)
            ->setData($data)
            ->save();
        }
      }
    }
  }

  /**
   * Install new field.
   *
   * @param string $config_location
   *   Absolute path to the configuration to be created.
   * @param string $field_storage
   *   Name of the field storage configuration to install.
   * @param string $field_config
   *   Name of the field configuration to install.
   */
  public static function installNewField(string $config_location, string $field_storage, string $field_config): void {
    $field_storage_path = "{$config_location}{$field_storage}.yml";

    // Install field storage configurations.
    if (isset($field_storage) && file_exists($field_storage_path)) {
      $storage_data = Yaml::parse(file_get_contents($field_storage_path));
      if (!FieldStorageConfig::loadByName($storage_data['entity_type'], $storage_data['field_name'])) {
        $allowed_values = FALSE;

        // Allowed values installation might end up in an error.
        // Install allowed values separately.
        if (
          isset($storage_data['settings']['allowed_values']) &&
          !empty($storage_data['settings']['allowed_values'])
        ) {
          $allowed_values = $storage_data['settings']['allowed_values'];
          unset($storage_data['settings']['allowed_values']);
        }

        // Create field storage configuration.
        FieldStorageConfig::create($storage_data)->save();

        // If allowed values is set, install them separately.
        if ($allowed_values) {
          $new_field_storage = \Drupal::configFactory()->getEditable($field_storage);
          $new_field_storage->set('settings.allowed_values', $allowed_values)->save();
        }
      }
    }

    $field_config_path = "{$config_location}{$field_config}.yml";

    // Install field configurations.
    if (isset($field_config) && file_exists($field_config_path)) {
      $field_data = Yaml::parse(file_get_contents($field_config_path));
      if (!FieldConfig::loadByName($field_data['entity_type'], $field_data['bundle'], $field_data['field_name'])) {
        FieldConfig::create($field_data)->save();
      }
    }
  }

  /**
   * Merge arrays recursively.
   *
   * Merges multiple arrays, recursively, and returns the merged array with
   * unique sequential arrays.
   *
   * @param array $arrays
   *   An arrays of arrays to merge.
   *
   * @return array
   *   The merged array.
   *
   * @see NestedArray::mergeDeepArray()
   */
  public static function mergeDeepArray(array $arrays): array {
    $result = [];
    foreach ($arrays as $array) {
      foreach ($array as $key => $value) {
        // Renumber integer keys as array_merge_recursive() does unless
        // $preserve_integer_keys is set to TRUE. Note that PHP automatically
        // converts array keys that are integer strings (e.g., '1') to integers.
        if (is_int($key)) {
          $result[] = $value;
        }
        // Recurse when both values are arrays.
        elseif (isset($result[$key]) && is_array($result[$key]) && is_array($value)) {
          $result[$key] = self::mergeDeepArray([$result[$key], $value]);
        }
        // Otherwise, use the latter value, overriding any previous value.
        else {
          $result[$key] = $value;
        }
      }

      // Use array_unique if resulting array is sequential array.
      if (!self::hasStringKeys($result)) {
        $result = array_values(array_unique($result));
      }
    }
    return $result;
  }

  /**
   * Helper function to check if array has non-integer keys.
   *
   * @param array $array
   *   Array to check.
   *
   * @return bool
   *   Returns true if array has non-integer keys, otherwise false.
   */
  protected static function hasStringKeys(array $array): bool {
    return count(array_filter(array_keys($array), 'is_string')) > 0;
  }

}
