<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_curated_event_list\Kernel;

use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\helfi_paragraphs_curated_event_list\Entity\LinkedEventsEvent;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests Curated event External entity.
 */
#[Group('helfi_paragraphs_curated_event_list')]
#[RunTestsInSeparateProcesses]
class EventEntityTest extends KernelTestBase {

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
    'helfi_paragraphs_curated_event_list',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Triggers rebuilding routes.
    // https://www.drupal.org/project/external_entities/issues/3549828.
    $this->container
      ->get(RouteProviderInterface::class)
      ->getAllRoutes();

    $this->installConfig(['external_entities', 'helfi_paragraphs_curated_event_list']);
    $this->installEntitySchema('linkedevents_event');
  }

  /**
   * Tests hasEnded() method.
   */
  #[Test]
  #[DataProvider('hasEndedDataProvider')]
  public function testHasEnded(?string $endTime, bool $expected): void {
    $client = $this->setupMockHttpClient([
      new Response(body: json_encode([
        'data' => [
          [
            'id' => 'helsinki:agnjd4b73u',
            'name' => [
              'en' => 'Title',
            ],
            'start_time' => 'now',
            'end_time' => $endTime,
          ],
        ],
      ])),
    ]);
    $this->container->set('http_client', $client);

    $entity = LinkedEventsEvent::load('helsinki:agnjd4b73u');
    $this->assertEquals($expected, $entity->hasEnded());
  }

  /**
   * Data provider for testHasEnded().
   *
   * @return array
   *   The data.
   */
  public static function hasEndedDataProvider(): array {
    return [
      ['now', FALSE],
      // Should be expired because the end time is in the past.
      ['-1 seconds', TRUE],
      // Should not be expired because the end time is in the future.
      ['+1 seconds', FALSE],
      // Should not expire because end_time is not set.
      [NULL, FALSE],
    ];
  }

}
