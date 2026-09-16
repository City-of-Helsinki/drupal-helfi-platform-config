<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_react_search\Unit;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_react_search\Enum\CourseCategory;
use Drupal\helfi_react_search\Enum\EventCategory;
use Drupal\helfi_react_search\Enum\EventListCategoryInterface;
use Drupal\helfi_react_search\Enum\Filters;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

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
   */
  #[DataProvider('enumDataProvider')]
  public function testEnum(string $class) {
    $this->assertTrue(enum_exists($class));

    foreach (call_user_func([$class, 'cases']) as $case) {
      $this->assertInstanceOf(EventListCategoryInterface::class, $case);
      $this->assertInstanceOf(TranslatableMarkup::class, $case->translation());
      $this->assertIsArray($case->keywords());
    }
  }

  /**
   * Data provider for the enum test.
   *
   * @phpstan-return array<array<class-string>>
   */
  public static function enumDataProvider(): array {
    return [
      [EventCategory::class],
      [CourseCategory::class],
    ];
  }

  /**
   * Tests Drupal setting name for each filter.
   */
  #[DataProvider('drupalSettingNameProvider')]
  public function testDrupalSettingName(Filters $filter, string $expected): void {
    $this->assertSame($expected, $filter->drupalSettingName());
  }

  /**
   * Data provider for Drupal setting names.
   *
   * @phpstan-return array<int, list<Filters|string>>
   */
  public static function drupalSettingNameProvider(): array {
    return [
      [Filters::Locations, 'field_event_location'],
      [Filters::EventTime, 'field_event_time'],
      [Filters::FreeEvents, 'field_free_events'],
      [Filters::RemoteEvents, 'field_remote_events'],
      [Filters::Language, 'field_language'],
      [Filters::SearchTerm, 'field_search_term'],
      [Filters::TargetGroup, 'useTargetGroupFilter'],
    ];
  }

}
