<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_react_search\Kernel;

use Drupal\helfi_react_search\DTO\LinkedEventsItem;
use Drupal\helfi_react_search\Entity\EventList;
use Drupal\helfi_react_search\Enum\EventCategory;
use Drupal\helfi_react_search\EventListUpdateHelper;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use GuzzleHttp\Psr7\Response;

/**
 * Event list update helper test.
 */
class EventListUpdateHelperTest extends KernelTestBase {

  use ApiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
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
   * Tests update helper.
   */
  public function testEventListUpdateHelper(): void {
    $client = $this->createMockHttpClient([
      new Response(body: json_encode([
        'id' => 'yso:p6357',
        "name" => [
          "fi" => "työllisyys",
          "sv" => "sysselsättning (tillstånd)",
          "en" => "employment",
        ],
      ])),
      new Response(body: json_encode([
        'id' => 'tprek:8143',
        "name" => [
          "fi" => "test (fi)",
          "sv" => "test (sv)",
          "en" => "test (en)",
        ],
      ])),
      new Response(body: json_encode([
        'id' => 'tprek:68554',
        "name" => [
          "fi" => "test (fi)",
          "sv" => "test (sv)",
          "en" => "test (en)",
        ],
      ])),
    ]);
    $sut = new EventListUpdateHelper(
      $client,
      $this->container->get('serializer'),
    );

    $paragraph = EventList::create([
      'type' => 'event_list',
      'field_api_url' => 'https://tapahtumat.hel.fi/fi/haku?categories=movie&keyword=yso:p6357',
    ]);
    $this->assertTrue($sut->migrateApiUrl($paragraph));
    $this->assertParagraph($paragraph, categories: [EventCategory::Movie], keywords: ['yso:p6357']);

    $paragraph = EventList::create([
      'type' => 'event_list',
      'field_api_url' => 'https://tapahtumat.hel.fi/fi/haku?text=test_text&categories=movie,culture&start=2025-01-31&divisions=test_division&dateTypes=today&isFree=true&onlyEveningEvents=true&onlyRemoteEvents=true&onlyChildrenEvents=true',
    ]);
    $this->assertTrue($sut->migrateApiUrl($paragraph));
    $this->assertParagraph(
      $paragraph,
      categories: [EventCategory::Movie, EventCategory::Culture],
      freeText: '?all_ongoing_AND=test_text&start=now&division=test_division&end=today&is_free=true&starts_after=16&internet_based=true&keyword_AND=yso%3Ap4354',
    );

    // Params without special handling should pass
    // through untouched.
    $paragraph = EventList::create([
      'type' => 'event_list',
      'field_api_url' => 'https://tapahtumat.hel.fi/fi/haku?test_param_1=test_value_1&test_param_2=test_value_2',
    ]);
    $this->assertTrue($sut->migrateApiUrl($paragraph));
    $this->assertParagraph(
      $paragraph,
      freeText: '?test_param_1=test_value_1&test_param_2=test_value_2',
    );

    $paragraph = EventList::create([
      'type' => 'event_list',
      'field_api_url' => 'https://tapahtumat.hel.fi/fi/haku?places=tprek%3A8143%2Ctprek%3A68554',
    ]);
    $this->assertTrue($sut->migrateApiUrl($paragraph));
    $this->assertParagraph(
      $paragraph,
      places: ['tprek:8143', 'tprek:68554'],
    );
  }

  /**
   * Assert that the given entity is set up correctly.
   */
  private function assertParagraph(EventList $paragraph, array $places = [], array $categories = [], array $keywords = [], ?string $freeText = NULL): void {
    foreach ($places as $index => $place) {
      $field = $paragraph->get('field_event_list_place')->get($index);
      $this->assertNotEmpty($field?->getString());

      /** @var \Drupal\helfi_react_search\DTO\LinkedEventsItem $item */
      $item = $this->container->get('serializer')
        ->deserialize($field->getString(), LinkedEventsItem::class, 'json');

      $this->assertEquals($place, $item->id);
    }

    foreach ($categories as $index => $category) {
      $field = $paragraph->get('field_event_list_category_event')->get($index);
      $this->assertNotEmpty($field?->getString());

      $this->assertEquals($category, EventCategory::tryFrom($field->getString()));
    }

    foreach ($keywords as $index => $keyword) {
      $field = $paragraph->get('field_event_list_keywords')->get($index);
      $this->assertNotEmpty($field?->getString());

      /** @var \Drupal\helfi_react_search\DTO\LinkedEventsItem $item */
      $item = $this->container->get('serializer')
        ->deserialize($field->getString(), LinkedEventsItem::class, 'json');

      $this->assertEquals($keyword, $item->id);
    }

    $this->assertEquals($freeText, $paragraph->get('field_event_list_free_text')?->getString());
  }

}
