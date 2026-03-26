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
 *
 * @group helfi_react_search
 */
class EventListTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_react_search',
    'helfi_api_base',
    'entity_reference_revisions',
    'user',
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
      'https://tapahtumat.hel.fi/fi/haku?categories=dance%2Cculture&keyword=yso%3Ap23&places=tprek%3A28473&fullText=jooga',
      $paragraph->getEventsPublicUrl()
    );
    $this->assertEquals(
      'https://harrastukset.hel.fi/fi/haku?keyword=yso%3Ap23&places=tprek%3A28473&fullText=jooga',
      $paragraph->getHobbiesPublicUrl()
    );

    $paragraph->set('field_event_list_free_text', '?full_text=jooga');
    $this->assertEquals(
      'https://tapahtumat.hel.fi/fi/haku?categories=dance%2Cculture&keyword=yso%3Ap23&places=tprek%3A28473&fullText=jooga',
      $paragraph->getEventsPublicUrl()
    );
    $this->assertEquals(
      'https://harrastukset.hel.fi/fi/haku?keyword=yso%3Ap23&places=tprek%3A28473&fullText=jooga',
      $paragraph->getHobbiesPublicUrl()
    );

    $paragraph->set('field_event_list_free_text', '?all_ongoing_AND=jooga');
    $this->assertEquals(
      'https://tapahtumat.hel.fi/fi/haku?categories=dance%2Cculture&keyword=yso%3Ap23&places=tprek%3A28473&fullText=jooga',
      $paragraph->getEventsPublicUrl()
    );
    $this->assertEquals(
      'https://harrastukset.hel.fi/fi/haku?keyword=yso%3Ap23&places=tprek%3A28473&fullText=jooga',
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

    $this->testGetApiUrl($paragraph);
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

  /**
   * Tests getApiUrl.
   */
  private function testGetApiUrl(EventList $paragraph): void {
    $base = 'https://api.hel.fi/linkedevents/v1/event/';
    // UrlHelper::buildQuery encodes commas in include/super_event_type and
    // colons in division; empty keyword/location still appear as
    // keyword=&location= in the query string.
    $emptyQuery = 'keyword=&location=&event_type=General&format=json&include=keywords%2Clocation&page=1&page_size=3&sort=end_time&start=now&super_event_type=umbrella%2Cnone&language=en&ongoing=true&division=kunta%3Ahelsinki';
    $this->assertSame($base . '?' . $emptyQuery, $paragraph->getApiUrl());

    $paragraph->set('field_event_list_keywords', [
      '{"id": "yso:p23", "name": {"en": "Test1"}}',
    ]);
    $paragraph->set('field_event_list_place', [
      '{"id": "tprek:28473", "name": {"en": "Test2"}}',
    ]);
    $paragraph->set('field_event_list_type', 'events');

    $eventsQuery = 'keyword=yso%3Ap23&location=tprek%3A28473&event_type=General&format=json&include=keywords%2Clocation&page=1&page_size=3&sort=end_time&start=now&super_event_type=umbrella%2Cnone&language=en&ongoing=true&division=kunta%3Ahelsinki';
    $this->assertSame($base . '?' . $eventsQuery, $paragraph->getApiUrl());

    $paragraph->set('field_event_list_type', 'hobbies');
    $hobbiesQuery = str_replace('event_type=General', 'event_type=Course', $eventsQuery);
    $this->assertSame($base . '?' . $hobbiesQuery, $paragraph->getApiUrl());

    $paragraph->set('field_event_list_type', 'events_and_hobbies');
    $bothQuery = str_replace('event_type=Course', 'event_type=General%2CCourse', $hobbiesQuery);
    $this->assertSame($base . '?' . $bothQuery, $paragraph->getApiUrl());

    // Category keywords are merged into the keyword parameter (after paragraph
    // keywords).
    $paragraph->set('field_event_list_type', 'events');
    $paragraph->set('field_event_list_category_event', [EventCategory::Movie->value]);
    $movieQuery = str_replace(
      'keyword=yso%3Ap23',
      'keyword=yso%3Ap23%2Cyso%3Ap1235',
      $eventsQuery
    );
    $this->assertSame($base . '?' . $movieQuery, $paragraph->getApiUrl());

    $paragraph->set('field_event_list_category_event', []);
    $paragraph->set('field_event_list_free_text', 'jooga');
    $eventsWithFullText = str_replace(
      'language=en&ongoing=true',
      'language=en&full_text=jooga&ongoing=true',
      $eventsQuery
    );
    $this->assertSame($base . '?' . $eventsWithFullText, $paragraph->getApiUrl());

    $paragraph->set('field_event_list_free_text', '?publisher=ahjo%3Au021200');
    $eventsWithPublisher = str_replace(
      'language=en&ongoing=true',
      'language=en&publisher=ahjo%3Au021200&ongoing=true',
      $eventsQuery
    );
    $this->assertSame($base . '?' . $eventsWithPublisher, $paragraph->getApiUrl());

    $paragraph->set('field_event_list_free_text', NULL);
    $this->assertStringContainsString('custom=value', $paragraph->getApiUrl(['custom' => 'value']));

    $url = $paragraph->getApiUrl(['all_ongoing_AND' => 'swimming']);
    $this->assertStringContainsString('full_text=swimming', $url);
    $this->assertStringNotContainsString('all_ongoing_AND', $url);

    $paragraph->set('field_event_list_free_text', 'jooga');
    $url = $paragraph->getApiUrl(['all_ongoing_AND' => 'swimming']);
    $this->assertStringContainsString('full_text=jooga%20swimming', $url);
  }

}
