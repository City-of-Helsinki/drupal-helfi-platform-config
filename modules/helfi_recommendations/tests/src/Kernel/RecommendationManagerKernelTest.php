<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_recommendations\Kernel;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\helfi_api_base\Cache\CacheTagInvalidator;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_recommendations\RecommendationManager;
use Drupal\helfi_recommendations\TopicsManagerInterface;
use Drupal\helfi_recommendations\Entity\SuggestedTopics;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\Tests\helfi_platform_config\Traits\ElasticTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Elastic\Elasticsearch\ClientBuilder;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Kernel tests for RecommendationManager.
 *
 * @group helfi_recommendations
 * @coversDefaultClass \Drupal\helfi_recommendations\RecommendationManager
 */
class RecommendationManagerKernelTest extends AnnifKernelTestBase {

  use EnvironmentResolverTrait;
  use NodeCreationTrait;
  use ProphecyTrait;
  use ApiTestTrait;
  use ElasticTrait;

  /**
   * Test environment.
   *
   * @var \Drupal\helfi_api_base\Environment\EnvironmentEnum
   */
  private EnvironmentEnum $environment = EnvironmentEnum::Local;

  /**
   * Additional modules to enable.
   *
   * @var string[]
   */
  protected static $modules = [
    'helfi_api_base',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->setActiveProject(Project::ETUSIVU, $this->environment);

    $user = $this->createUser();
    $this->container->get('current_user')->setAccount($user);
  }

  /**
   * Tests showRecommendations without suggested topics reference fields.
   */
  public function testShowRecommendationsWithoutFields(): void {
    NodeType::create([
      'name' => $this->randomMachineName(),
      'type' => 'another_test_node_bundle',
    ])->save();

    $node = Node::create([
      'type' => 'another_test_node_bundle',
      'title' => 'Test node',
    ]);
    $node->save();

    $recommendationManager = $this->getSut();
    $this->assertFalse($recommendationManager->showRecommendations($node));
  }

  /**
   * Tests showRecommendations with show_block = FALSE.
   */
  public function testShowRecommendationsWithShowBlockFalse(): void {
    $node = Node::create([
      'type' => 'test_node_bundle',
      'title' => $this->randomString(),
      'test_keywords' => [
        [
          'entity' => SuggestedTopics::create(),
          'show_block' => FALSE,
        ],
      ],
    ]);
    $node->save();

    $recommendationManager = $this->getSut();
    $this->assertFalse($recommendationManager->showRecommendations($node));
  }

  /**
   * Tests showRecommendations with show_block = TRUE.
   */
  public function testShowRecommendationsWithShowBlockTrue(): void {
    $node = Node::create([
      'type' => 'test_node_bundle',
      'title' => $this->randomString(),
      'test_keywords' => [
        [
          'entity' => SuggestedTopics::create(),
          'show_block' => TRUE,
        ],
      ],
    ]);
    $node->save();

    $recommendationManager = $this->getSut();
    $this->assertTrue($recommendationManager->showRecommendations($node));
  }

  /**
   * Tests getRecommendations with local entities and translations.
   */
  public function testGetRecommendationsWithTranslations(): void {
    $term1 = Term::create([
      'name' => 'foo',
      'vid' => 'test_vocabulary',
    ]);
    $term1->save();

    $term2 = Term::create([
      'name' => 'bar',
      'vid' => 'test_vocabulary',
    ]);
    $term2->save();

    $nodeSource = Node::create([
      'type' => 'test_node_bundle',
      'title' => $this->randomString(),
      'test_keywords' => SuggestedTopics::create([
        'keywords' => [
          ['entity' => $term1, 'score' => 0.8],
          ['entity' => $term2, 'score' => 0.2],
        ],
      ]),
    ]);
    $translationSource = $nodeSource->toArray();
    $nodeSource->addTranslation('sv', $translationSource);
    $nodeSource->save();

    $suggestionUUID = $this->randomString();
    $parentUrlEn = $this->randomString();
    $parentUrlSv = $this->randomString();
    $parentTitleEn = $this->randomString();
    $parentTitleSv = $this->randomString();

    $response = $this->createElasticsearchResponse([
      'hits' => [
        'hits' => [
          [
            '_source' => [
              'parent_instance' => [Project::ETUSIVU],
              'parent_type' => ['node'],
              'parent_bundle' => ['test_node_bundle'],
              'uuid' => [$suggestionUUID],
              'parent_url_en' => [$parentUrlEn],
              'parent_url_sv' => [$parentUrlSv],
              'parent_title_en' => [$parentTitleEn],
              'parent_title_sv' => [$parentTitleSv],
            ],
          ],
        ],
      ],
    ]);
    $recommendationManager = $this->getSut([$response, $response]);

    // Test recommendations in Finnish.
    $recommendations = $recommendationManager->getRecommendations($nodeSource);
    $this->assertEquals($parentTitleEn, $recommendations[0]['title']);
    $this->assertEquals($suggestionUUID, $recommendations[0]['uuid']);
    $this->assertEquals($parentUrlEn, $recommendations[0]['url']);

    // Test recommendations in Swedish.
    $recommendations = $recommendationManager->getRecommendations($nodeSource, 3, 'sv');
    $this->assertEquals($parentTitleSv, $recommendations[0]['title']);
    $this->assertEquals($suggestionUUID, $recommendations[0]['uuid']);
    $this->assertEquals($parentUrlSv, $recommendations[0]['url']);
  }

  /**
   * Tests getRecommendations with empty search results.
   */
  public function testGetRecommendationsWithEmptyResults(): void {
    $term1 = Term::create([
      'name' => 'foo',
      'vid' => 'test_vocabulary',
    ]);
    $term1->save();

    $term2 = Term::create([
      'name' => 'bar',
      'vid' => 'test_vocabulary',
    ]);
    $term2->save();

    $nodeSource = Node::create([
      'type' => 'test_node_bundle',
      'title' => $this->randomString(),
      'test_keywords' => SuggestedTopics::create([
        'keywords' => [
          ['entity' => $term1, 'score' => 0.8],
          ['entity' => $term2, 'score' => 0.2],
        ],
      ]),
    ]);
    $nodeSource->save();

    $responses = [
      $this->createElasticsearchResponse([
        'hits' => [
          'hits' => [],
        ],
      ]),
    ];
    $recommendationManager = $this->getSut($responses);

    // Test recommendations with empty results.
    $recommendations = $recommendationManager->getRecommendations($nodeSource);
    $this->assertEmpty($recommendations);
  }

  /**
   * Tests getRecommendations with external results.
   */
  public function testGetRecommendationsWithExternalResults(): void {
    $term1 = Term::create([
      'name' => 'foo',
      'vid' => 'test_vocabulary',
    ]);
    $term1->save();

    $term2 = Term::create([
      'name' => 'bar',
      'vid' => 'test_vocabulary',
    ]);
    $term2->save();

    $nodeSource = Node::create([
      'type' => 'test_node_bundle',
      'title' => $this->randomString(),
      'test_keywords' => SuggestedTopics::create([
        'keywords' => [
          ['entity' => $term1, 'score' => 0.8],
          ['entity' => $term2, 'score' => 0.2],
        ],
      ]),
    ]);
    $nodeSource->save();

    $resultUUID = $this->randomString();
    $resultTitle = $this->randomString();
    $resultUrl = $this->randomString();
    $responses = [
      $this->createElasticsearchResponse([
        'hits' => [
          'hits' => [
            [
              '_source' => [
                'parent_instance' => [Project::ASUMINEN],
                'parent_type' => ['node'],
                'parent_bundle' => ['test_node_bundle'],
                'parent_id' => [$nodeSource->id()],
                'uuid' => [$resultUUID],
                'parent_url_en' => [$resultUrl],
                'parent_title_en' => [$resultTitle],
              ],
            ],
          ],
        ],
      ]),
    ];
    $recommendationManager = $this->getSut($responses);

    $recommendations = $recommendationManager->getRecommendations($nodeSource);
    $this->assertEquals($resultTitle, $recommendations[0]['title']);
    $this->assertEquals($resultUUID, $recommendations[0]['uuid']);
    $this->assertEquals($resultUrl, $recommendations[0]['url']);
  }

  /**
   * Gets service under test.
   *
   * @param array $responses
   *   The elasticsearch mock data.
   *
   * @return \Drupal\helfi_recommendations\RecommendationManager
   *   The service under test.
   */
  private function getSut(
    array $responses = [],
  ): RecommendationManager {
    $loggerChannel = $this->prophesize(LoggerChannelInterface::class);

    $mock = $this->createMockHttpClient($responses);
    $client = ClientBuilder::create()
      ->setHttpClient($mock)
      ->build();

    return new RecommendationManager(
      $loggerChannel->reveal(),
      $this->container->get(EnvironmentResolverInterface::class),
      $this->container->get(TopicsManagerInterface::class),
      $client,
      $this->container->get(CacheTagInvalidator::class),
      $this->container->get('state'),
      $this->container->get('string_translation'),
    );
  }

}
