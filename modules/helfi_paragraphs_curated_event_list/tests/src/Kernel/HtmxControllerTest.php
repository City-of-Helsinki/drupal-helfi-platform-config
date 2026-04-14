<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_curated_event_list\Kernel;

use Drupal\Core\Render\HtmlResponse;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Url;
use Drupal\helfi_paragraphs_curated_event_list\Controller\HtmxController;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\helfi_api_base\Traits\LanguageManagerTrait;
use Drupal\Tests\paragraphs\FunctionalJavascript\ParagraphsTestBaseTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests Curated event list HtmxController.
 */
#[Group('helfi_paragraphs_curated_event_list')]
#[RunTestsInSeparateProcesses]
class HtmxControllerTest extends KernelTestBase {

  use ApiTestTrait;
  use ParagraphsTestBaseTrait;
  use UserCreationTrait;
  use LanguageManagerTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'helfi_api_base',
    'config_rewrite',
    'language',
    'helfi_language_negotiator_test',
    'content_translation',
    'helfi_platform_config',
    'entity_reference_revisions',
    'field',
    'file',
    'linkit',
    'breakpoint',
    'responsive_image',
    'link',
    'datetime',
    'user',
    'imagecache_external',
    'paragraphs',
    'external_entities',
    'node',
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

    $this->installConfig(['system', 'user', 'paragraphs', 'external_entities']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('paragraphs_type');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig('helfi_paragraphs_curated_event_list');
    $this->installEntitySchema('linkedevents_event');

    $this->addParagraphedContentType('article');

    $this->setupLanguages();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);

    Role::load(RoleInterface::ANONYMOUS_ID)
      ->grantPermission('access content')
      ->save();
  }

  /**
   * Creates a HTMX request.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph to create request for.
   *
   * @return \Drupal\Core\Render\HtmlResponse
   *   The HTML response.
   */
  private function createHtmxRequest(ParagraphInterface $paragraph): HtmlResponse {
    $parameters = [
      'paragraph' => $paragraph->id(),
    ];
    $url = (new Url('helfi_paragraphs_curated_event_list.htmx', $parameters))
      ->toString();
    /** @var \Drupal\Core\Render\HtmlResponse $response */
    $response = $this->processRequest($this->getMockedRequest($url));
    return $response;
  }

  /**
   * Tests unpublished parent entity.
   */
  #[Test]
  public function testAccessDenied(): void {
    $client = $this->setupMockHttpClient([
      new Response(body: (string) json_encode([
        'data' => [
          [
            'id' => 'helsinki:agnjd4b73u',
            'name' => [
              'fi' => 'Title fi',
              'en' => 'Title en',
            ],
            'start_time' => 'now',
          ],
        ],
      ])),
    ]);
    $this->container->set('http_client', $client);

    $paragraph = Paragraph::create([
      'type' => 'curated_event_list',
      'field_events' => [
        ['target_id' => 'helsinki:agnjd4b73u'],
      ],
    ]);
    $paragraph->setPublished();
    $paragraph->save();
    $node = Node::create([
      'title' => 'Events list',
      'type' => 'article',
      'field_paragraphs' => [$paragraph],
    ]);
    $node->setUnpublished();
    $node->save();

    $response = $this->createHtmxRequest($paragraph);
    $this->assertEquals(403, $response->getStatusCode());

    // Entity access is statically cached, make sure it's cleared.
    // @see \Drupal\Core\Entity\EntityAccessControlHandler::getCache().
    \Drupal::entityTypeManager()->clearCachedDefinitions();

    // Publish node and make sure we have access to it.
    $node->setPublished()->save();
    $response = $this->createHtmxRequest($paragraph);
    $this->assertEquals(200, $response->getStatusCode());
  }

  /**
   * Make sure expired items are not shown.
   */
  #[Test]
  public function testExpiredEntity(): void {
    $client = $this->setupMockHttpClient([
      new Response(body: (string) json_encode([
        'data' => [
          [
            'id' => 'helsinki:agnjd4b73u',
            'name' => [
              'en' => 'Title en',
              'fi' => 'Title fi',
            ],
            'start_time' => 'now',
            'end_time' => '-1 second',
          ],
        ],
      ])),
    ]);
    $this->container->set('http_client', $client);

    $paragraph = Paragraph::create([
      'type' => 'curated_event_list',
      'field_events' => [
        ['target_id' => 'helsinki:agnjd4b73u'],
      ],
    ]);
    $paragraph->save();
    $node = Node::create([
      'title' => 'Events list',
      'type' => 'article',
      'field_paragraphs' => [$paragraph],
    ]);
    $node->setPublished();
    $node->save();

    $response = $this->createHtmxRequest($paragraph);
    $cache = $response->getCacheableMetadata();
    $this->assertEquals(HtmxController::MAX_AGE, $cache->getCacheMaxAge());
    $this->assertStringContainsString('Recommended events were not found', (string) $response->getContent());
    $this->assertEquals(200, $response->getStatusCode());
  }

  /**
   * Tests response with an expired and an active event.
   */
  #[Test]
  public function testExpiredAndActive(): void {
    $client = $this->setupMockHttpClient([
      new Response(body: (string) json_encode([
        'data' => [
          [
            'id' => 'helsinki:321',
            'name' => [
              'en' => 'Title expired',
            ],
            'start_time' => 'now',
            'end_time' => '-1 day',
          ],
          [
            'id' => 'helsinki:123',
            'name' => [
              'en' => 'Title active',
            ],
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
        ['target_id' => 'helsinki:321,en'],
        ['target_id' => 'helsinki:123,en'],
      ],
    ]);
    $paragraph->save();
    $node = Node::create([
      'title' => 'Events list',
      'type' => 'article',
      'field_paragraphs' => [$paragraph],
    ]);
    $node->setPublished();
    $node->save();

    $response = $this->createHtmxRequest($paragraph);
    $this->assertStringContainsString('Title active', (string) $response->getContent());
    $this->assertStringNotContainsString('Title expired', (string) $response->getContent());
    $cache = $response->getCacheableMetadata();

    // Make sure max age is 1d + 5 seconds because that's when the event
    // ends.
    $this->assertEquals(86405, $cache->getCacheMaxAge());
    $this->assertEquals(200, $response->getStatusCode());
  }

  /**
   * Tests translation support.
   */
  #[Test]
  public function testTranslation(): void {
    $paragraphs = $responses = [];

    foreach (['sv', 'fi', 'en'] as $langcode) {
      // Each language is queried three times.
      for ($i = 0; $i < 3; $i++) {
        $responses[] = new Response(body: (string) json_encode([
          'data' => [
            [
              'id' => 'helsinki:123',
              'name' => [
                $langcode => 'Title ' . $langcode,
              ],
              'start_time' => 'now',
              'end_time' => '+1 day',
            ],
          ],
        ]));
      }

      $paragraphs[$langcode] = Paragraph::create([
        'type' => 'curated_event_list',
        'langcode' => $langcode,
        'field_events' => [
          ['target_id' => 'helsinki:123,' . $langcode],
        ],
      ]);
      $paragraphs[$langcode]->save();
    }
    $node = Node::create([
      'title' => 'Events list en',
      'langcode' => 'en',
      'type' => 'article',
      'field_paragraphs' => [$paragraphs['en']],
    ]);
    $node->setPublished();
    $node->save();

    $node->addTranslation('fi', [
      'title' => 'Event list fi',
      'field_paragraphs' => [$paragraphs['fi']],
    ]);
    $node->addTranslation('sv', [
      'title' => 'Event list sv',
      'field_paragraphs' => [$paragraphs['sv']],
    ]);
    $node->save();

    $client = $this->setupMockHttpClient($responses);
    $this->container->set('http_client', $client);

    foreach (['sv', 'fi', 'en'] as $langcode) {
      $this->setOverrideLanguageCode($langcode);
      $response = $this->createHtmxRequest($paragraphs[$langcode]);

      $this->assertStringContainsString('Title ' . $langcode, (string) $response->getContent());
      $cache = $response->getCacheableMetadata();
      $this->assertEquals(200, $response->getStatusCode());

      // Make sure all translations are cached, but running tests can take some
      // time so give it some leeway.
      $this->assertTrue($cache->getCacheMaxAge() > 86390);
    }
  }

}
