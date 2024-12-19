<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\ConfigUpdate;

/**
 * Helper class to deal with config merging.
 */
class ConfigMergeHelper {

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
        // Renumber integer keys as array_merge_recursive() does. Note that PHP
        // automatically converts array keys that are integer strings
        // (e.g., '1') to integers.
        if (is_int($key)) {
          $result[] = $value;
        }
        // Recurse when both values are arrays.
        elseif (isset($result[$key]) && is_array($result[$key]) && is_array($value)) {
          $result[$key] = static::mergeDeepArray([$result[$key], $value]);
        }
        // Otherwise, use the latter value, overriding any previous value.
        else {
          $result[$key] = $value;
        }
      }

      // Use array_unique if resulting array is sequential array.
      if (is_array($result) && !static::hasStringKeys($result)) {
        $result = array_values(static::arrayUniqueMultidimensional($result));
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

  /**
   * Remove duplicates from multidimensional array.
   *
   * @param array $input
   *   Array to handle.
   *
   * @return array
   *   Returns the handled array.
   */
  protected static function arrayUniqueMultidimensional(array $input): array {
    $serialized = array_map('serialize', $input);
    $unique = array_unique($serialized);
    return array_intersect_key($input, $unique);
  }

}
