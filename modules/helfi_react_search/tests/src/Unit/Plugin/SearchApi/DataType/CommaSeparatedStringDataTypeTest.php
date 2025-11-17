<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_react_search\Unit\Plugin\SearchApi\DataType;

use Drupal\helfi_react_search\Plugin\search_api\data_type\CommaSeparatedStringDataType;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the CommaSeparatedStringDataType.
 *
 * @group helfi_react_search
 */
class CommaSeparatedStringDataTypeTest extends UnitTestCase {

  /**
   * Tests the getValue() method.
   */
  public function testCommaSeparatedStringDataType() {
    $dataType = new CommaSeparatedStringDataType([], 'comma_separated_string', []);
    $this->assertEquals(['test'], $dataType->getValue('test'));
    $this->assertEquals(['test', 'test2'], $dataType->getValue('test, test2'));
    $this->assertEquals(['test', 'test2', 'test3'], $dataType->getValue('test, test2, test3'));
  }

}
