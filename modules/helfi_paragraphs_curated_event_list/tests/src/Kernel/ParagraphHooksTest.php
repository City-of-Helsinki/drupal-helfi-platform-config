<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_curated_event_list\Kernel;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests ParagraphHooks.
 */
#[Group('helfi_paragraphs_curated_event_list')]
#[RunTestsInSeparateProcesses]
class ParagraphHooksTest extends KernelTestBase {

  use ApiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'helfi_api_base',
    'config_rewrite',
    'language',
    'content_translation',
    'helfi_platform_config',
    'entity_reference_revisions',
    'field',
    'file',
    'paragraphs',
    'linkit',
    'breakpoint',
    'responsive_image',
    'link',
    'user',
    'datetime',
    'imagecache_external',
    'external_entities',
    'text',
    'helfi_paragraphs_curated_event_list',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Triggers rebuilding routes.
    // @see https://www.drupal.org/project/external_entities/issues/3549828.
    $this->container
      ->get(RouteProviderInterface::class)
      ->getAllRoutes();

    $this->installConfig(['system', 'external_entities']);
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('paragraphs_type');
    $this->installConfig('helfi_paragraphs_curated_event_list');
    $this->installEntitySchema('linkedevents_event');
  }

  /**
   * Gets the status messages added to the messenger.
   *
   * @return string[]
   *   The status messages, as strings.
   */
  private function getStatusMessages(): array {
    $messages = $this->container->get('messenger')->all();

    if (empty($messages[MessengerInterface::TYPE_STATUS])) {
      return [];
    }
    return array_map('strval', $messages[MessengerInterface::TYPE_STATUS]);
  }

  /**
   * Tests that ended events are removed from the field on save.
   */
  #[Test]
  public function testEndedEventsAreRemoved(): void {
    $client = $this->setupMockHttpClient([
      new Response(body: (string) json_encode([
        'data' => [
          [
            'id' => 'helsinki:active',
            'name' => ['en' => 'Active event'],
            'start_time' => 'now',
            'end_time' => '+1 day',
          ],
          [
            'id' => 'helsinki:ended',
            'name' => ['en' => 'Ended event'],
            'start_time' => 'now',
            'end_time' => '-1 day',
          ],
        ],
      ])),
    ]);
    $this->container->set('http_client', $client);

    $paragraph = Paragraph::create([
      'type' => 'curated_event_list',
      'field_events' => [
        ['target_id' => 'helsinki:active,en'],
        ['target_id' => 'helsinki:ended,en'],
      ],
    ]);
    $paragraph->save();

    $remainingIds = array_column($paragraph->get('field_events')->getValue(), 'target_id');
    $this->assertSame(['helsinki:active,en'], $remainingIds);

    $messages = $this->getStatusMessages();
    $this->assertCount(1, $messages);
    $this->assertStringContainsString('Removed "Ended event', $messages[0]);
    $this->assertStringContainsString('" because the event has ended.', $messages[0]);
  }

  /**
   * Tests saving when no events have ended.
   */
  #[Test]
  public function testNoEndedEvents(): void {
    $client = $this->setupMockHttpClient([
      new Response(body: (string) json_encode([
        'data' => [
          [
            'id' => 'helsinki:active',
            'name' => ['en' => 'Active event'],
            'start_time' => 'now',
            'end_time' => '+1 day',
          ],
        ],
      ])),
    ]);
    $this->container->set('http_client', $client);

    $paragraph = Paragraph::create([
      'type' => 'curated_event_list',
      'field_events' => [
        ['target_id' => 'helsinki:active,en'],
      ],
    ]);
    $paragraph->save();

    $remainingIds = array_column($paragraph->get('field_events')->getValue(), 'target_id');
    $this->assertSame(['helsinki:active,en'], $remainingIds);
    $this->assertEmpty($this->getStatusMessages());
  }

  /**
   * Tests that paragraphs of other bundles are left untouched.
   */
  #[Test]
  public function testOtherBundleIsIgnored(): void {
    ParagraphsType::create(['id' => 'text', 'label' => 'Text'])->save();

    $paragraph = Paragraph::create(['type' => 'text']);
    $paragraph->save();

    $this->assertEmpty($this->getStatusMessages());
  }

}
