<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_react_search\Kernel\Entity;

use Drupal\helfi_react_search\DTO\LinkedEventsItem;
use Drupal\helfi_react_search\Entity\EventList;
use Drupal\helfi_react_search\Enum\EventCategory;
use Drupal\helfi_react_search\Enum\Filters;
use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Tests event list bundle class.
 */
class EventListTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_platform_config',
    'helfi_react_search',
    'helfi_api_base',
    'paragraphs',
    'field',
    'options',
    'link',
    'file',
    'system',
    'hdbt_admin_tools',
    'readonly_field_widget',
    'text',
    'select2',
    'serialization',
    'config_rewrite',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('paragraph');
    $this->installConfig('helfi_react_search');
  }

  /**
   * Tests URL getters.
   */
  public function testUrls(): void {
    $paragraph = Paragraph::create([
      'type' => 'event_list',
    ]);

    $this->assertInstanceOf(EventList::class, $paragraph);
    $this->assertEquals('https://tapahtumat.hel.fi/fi/haku', $paragraph->getEventsPublicUrl());

    $paragraph->set('field_event_list_category_event', [
      EventCategory::Dance->value,
      EventCategory::Culture->value,
    ]);
    $this->assertEquals(
      'https://tapahtumat.hel.fi/fi/haku?categories=dance%2Cculture',
      $paragraph->getEventsPublicUrl()
    );

    $paragraph->set('field_event_list_keywords', [
      '{"id": "yso:p23", "name": {"en": "Test1"}}',
    ]);
    $this->assertEquals(
      'https://tapahtumat.hel.fi/fi/haku?categories=dance%2Cculture&keyword=yso%3Ap23',
      $paragraph->getEventsPublicUrl()
    );

    $paragraph->set('field_event_list_place', [
      '{"id": "tprek:28473", "name": {"en": "Test2"}}',
    ]);
    $this->assertEquals(
      'https://tapahtumat.hel.fi/fi/haku?categories=dance%2Cculture&keyword=yso%3Ap23&places=tprek%3A28473',
      $paragraph->getEventsPublicUrl()
    );

    $paragraph->set('field_event_list_free_text', '?publisher=ahjo%3Au021200');
    $this->assertEquals(
      'https://tapahtumat.hel.fi/fi/haku?categories=dance%2Cculture&keyword=yso%3Ap23&places=tprek%3A28473&publisher=ahjo%3Au021200',
      $paragraph->getEventsPublicUrl()
    );

    $paragraph->set('field_event_list_free_text', 'jooga');
    $this->assertEquals(
      'https://tapahtumat.hel.fi/fi/haku?categories=dance%2Cculture&keyword=yso%3Ap23&places=tprek%3A28473&text=jooga',
      $paragraph->getEventsPublicUrl()
    );
    $this->assertEquals(
      'https://harrastukset.hel.fi/fi/haku?keyword=yso%3Ap23&places=tprek%3A28473&text=jooga',
      $paragraph->getHobbiesPublicUrl()
    );
  }

  /**
   * Tests event list bundle class.
   */
  public function testEventList() {
    $paragraph = Paragraph::create([
      'type' => 'event_list',
    ]);

    $this->assertInstanceOf(EventList::class, $paragraph);

    $this->testGetters($paragraph);
    $this->testGetFilterKeywords($paragraph);
    $this->testFilterSettings($paragraph);
  }

  /**
   * Tests getters.
   *
   * @param \Drupal\helfi_react_search\Entity\EventList $paragraph
   *   The paragraph.
   */
  private function testGetters(EventList $paragraph): void {
    // Test item count.
    $this->assertEquals(3, $paragraph->getCount());
    $paragraph->set('field_event_count', 6);
    $this->assertEquals(6, $paragraph->getCount());

    // Test title.
    $this->assertEmpty($paragraph->getTitle());
    $paragraph->set('field_event_list_title', 'Test title');
    $this->assertEquals('Test title', $paragraph->getTitle());
  }

  /**
   * Tests getFilterKeywords.
   *
   * @param \Drupal\helfi_react_search\Entity\EventList $paragraph
   *   The paragraph.
   */
  private function testGetFilterKeywords(EventList $paragraph): void {
    $this->assertEmpty($paragraph->getFilterKeywords());

    $paragraph->set('field_event_list_keywords_filter', 'invalid-json');
    $this->assertEmpty($paragraph->getFilterKeywords());

    $paragraph->set('field_event_list_keywords_filter', [
      '{"id": "test1", "name": {"en": "Test1"}}',
      'invalid-json',
      '{"id": "test2", "name": {"en": "Test2"}}',
    ]);
    $items = $paragraph->getFilterKeywords();
    $this->assertCount(2, $items);
    foreach ($items as $item) {
      $this->assertInstanceOf(LinkedEventsItem::class, $item);
      $this->assertStringContainsString('test', $item->id);
    }
  }

  /**
   * Tests filter settings method.
   *
   * @param \Drupal\helfi_react_search\Entity\EventList $paragraph
   *   The paragraph.
   */
  private function testFilterSettings(EventList $paragraph): void {
    // Paragraph must have a field that corresponds each filter value.
    foreach (Filters::cases() as $case) {
      $this->assertTrue($paragraph->hasField($case->value));
    }

    // Field values are not set.
    $settings = $paragraph->getFilterSettings();
    foreach (Filters::cases() as $case) {
      $this->assertFalse($settings[$case->value]);

      // Enable the settings.
      $paragraph->set($case->value, TRUE);
    }

    // Field values should be enabled.
    $settings = $paragraph->getFilterSettings();
    foreach (Filters::cases() as $case) {
      $this->assertTrue($settings[$case->value]);
    }
  }

}
