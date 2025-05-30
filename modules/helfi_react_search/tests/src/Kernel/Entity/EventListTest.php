<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_react_search\Kernel\Entity;

use Drupal\helfi_react_search\Entity\EventList;
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
    'helfi_react_search',
    'helfi_api_base',
    'paragraphs',
    'field',
    'options',
    'link',
    'file',
    'system',
    'taxonomy',
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
   * Tests event list bundle class.
   */
  public function testEventList() {
    $paragraph = Paragraph::create([
      'type' => 'event_list',
    ]);

    $this->assertInstanceOf(EventList::class, $paragraph);

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
