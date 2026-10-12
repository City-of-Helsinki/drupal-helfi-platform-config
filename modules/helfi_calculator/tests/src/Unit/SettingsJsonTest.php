<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_calculator\Unit;

use Drupal\helfi_calculator\SettingsJson;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the calculator settings JSON validation and sanitization.
 */
#[Group('helfi_calculator')]
final class SettingsJsonTest extends UnitTestCase {

  /**
   * Tests the validation.
   */
  #[DataProvider('validationData')]
  public function testIsValid(string $json, bool $expected) : void {
    $this->assertSame($expected, SettingsJson::isValid($json));
  }

  /**
   * The data provider for testIsValid().
   *
   * @return array<string, array{string, bool}>
   *   The data.
   */
  public static function validationData() : array {
    return [
      'empty string' => ['', TRUE],
      'empty object' => ['{}', TRUE],
      'numbers' => ['{"guardianship_fee": 37.5, "limits": {"min": 0, "max": 1000}, "income_limit": [1000, "1200.50"], "enabled": true, "other": null}', TRUE],
      'text' => ['{"title": "Asumismaksu <strong>2026</strong>"}', TRUE],
      'number keys' => ['{"safetyphone_limits": {"1": {"0": 35.68, "1489.01": 71.36}}, "voucher_from_net_income": {"900.01": 3424}}', TRUE],
      'prices as text' => ['{"single_low_income": "35,68", "guardianship_fee": 43.34}', TRUE],
      'invalid JSON' => ['{"guardianship_fee": ', FALSE],
      'scalar' => ['37.5', FALSE],
      'HTML in a key' => ['{"<img src=x onerror=alert(1)>": 1}', FALSE],
      'HTML in a nested key' => ['{"limits": {"max value": 1}}', FALSE],
    ];
  }

  /**
   * Tests the sanitization.
   */
  #[DataProvider('sanitizationData')]
  public function testSanitize(string $json, ?string $expected) : void {
    $this->assertSame($expected, SettingsJson::sanitize($json));
  }

  /**
   * The data provider for testSanitize().
   *
   * @return array<string, array{string, string|null}>
   *   The data.
   */
  public static function sanitizationData() : array {
    // The JSON is kept as it is when there is nothing to filter.
    $numbers = '{"guardianship_fee": 37.5, "limits": {"min": 0, "max": 1000.0}, "income_limit": [1000, "1200.50"], "safetyphone_limits": {"1": {"0": 35.68, "1489.01": 71.36}}, "single_low_income": "35,68", "empty": {}}';

    return [
      'empty string' => ['', ''],
      'empty object' => ['{}', '{}'],
      'numbers' => [$numbers, $numbers],
      'script' => ['{"guardianship_fee": "1<img src=x onerror=alert(1)>", "max": 1000.0, "empty": {}}', '{"guardianship_fee":"1","max":1000.0,"empty":{}}'],
      'nested' => ['{"texts": ["<script>alert(1)</script>Ok"]}', '{"texts":["alert(1)Ok"]}'],
      'allowed markup' => ['{"title": "<strong>Ä</strong> <a href=\"javascript:alert(1)\">x</a>"}', '{"title":"<strong>Ä<\/strong> <a href=\"alert(1)\">x<\/a>"}'],
      'invalid JSON' => ['{"guardianship_fee": ', NULL],
      'HTML in a key' => ['{"<img src=x onerror=alert(1)>": 1}', NULL],
    ];
  }

}
