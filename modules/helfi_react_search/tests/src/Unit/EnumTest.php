<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_react_search\Unit;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_react_search\Enum\CourseCategory;
use Drupal\helfi_react_search\Enum\EventCategory;
use Drupal\helfi_react_search\Enum\EventListCategoryInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests enum classes.
 *
 * @group helfi_react_search
 */
class EnumTest extends UnitTestCase {

  /**
   * Tests enum interface.
   *
   * @param class-string $class
   *   Enum class.
   *
   * @dataProvider dataProvider
   */
  public function testEnum(string $class) {
    $this->assertTrue(enum_exists($class));

    foreach (call_user_func([$class, 'cases']) as $case) {
      $this->assertInstanceOf(EventListCategoryInterface::class, $case);
      $this->assertInstanceOf(TranslatableMarkup::class, $case->translation());
      $this->assertIsArray($case->keywords());
    }
  }

  /**
   * Data provider for the test.
   */
  protected function dataProvider(): array {
    return [
      [EventCategory::class],
      [CourseCategory::class],
    ];
  }

}
