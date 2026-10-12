<?php

declare(strict_types=1);

namespace Drupal\helfi_calculator;

use Drupal\Component\Utility\Xss;

/**
 * Validates and sanitizes the calculator settings JSON.
 *
 * The browser inserts some of the settings values into HTML, so the text
 * values are filtered for XSS before they are output.
 */
final class SettingsJson {

  /**
   * Checks whether the settings JSON can be used.
   *
   * @param string $json
   *   The settings JSON. An empty string means no settings.
   *
   * @return bool
   *   TRUE if the JSON is valid and every key is a simple name.
   */
  public static function isValid(string $json) : bool {
    return self::decode($json) !== NULL;
  }

  /**
   * Filters the text values of the settings JSON for XSS.
   *
   * @param string $json
   *   The settings JSON.
   *
   * @return string|null
   *   The settings JSON with the text values filtered, or NULL if the JSON
   *   is not valid.
   */
  public static function sanitize(string $json) : ?string {
    if (trim($json) === '') {
      return $json;
    }
    if (($data = self::decode($json)) === NULL) {
      return NULL;
    }
    $filtered = self::filter($data);

    // Keep the original formatting when there is nothing to filter.
    if ($filtered == $data) {
      return $json;
    }
    return json_encode($filtered, JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
  }

  /**
   * Decodes the settings JSON.
   *
   * @param string $json
   *   The settings JSON.
   *
   * @return array<mixed>|object|null
   *   The decoded settings, or NULL if the JSON is not valid or a key is not
   *   a simple name.
   */
  private static function decode(string $json) : array|object|null {
    if (trim($json) === '') {
      return [];
    }

    try {
      // Decode the objects as objects, so that '{}' stays an object.
      $data = json_decode($json, flags: JSON_THROW_ON_ERROR);
    }
    catch (\JsonException) {
      return NULL;
    }
    return (is_array($data) || is_object($data)) && self::hasValidKeys($data) ? $data : NULL;
  }

  /**
   * Checks that every key is a simple name or a list index.
   *
   * @param array<mixed>|object $data
   *   The decoded settings.
   *
   * @return bool
   *   TRUE if the keys are valid.
   */
  private static function hasValidKeys(array|object $data) : bool {
    foreach ((array) $data as $key => $value) {
      if (is_string($key) && !preg_match('/^[A-Za-z0-9_]+$/', $key)) {
        return FALSE;
      }
      if ((is_array($value) || is_object($value)) && !self::hasValidKeys($value)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Filters the text values recursively.
   *
   * @param mixed $value
   *   The value.
   *
   * @return mixed
   *   The filtered value.
   */
  private static function filter(mixed $value) : mixed {
    if (is_string($value)) {
      return Xss::filter($value);
    }
    if (is_array($value)) {
      return array_map(self::filter(...), $value);
    }
    if (is_object($value)) {
      return (object) array_map(self::filter(...), (array) $value);
    }
    return $value;
  }

}
