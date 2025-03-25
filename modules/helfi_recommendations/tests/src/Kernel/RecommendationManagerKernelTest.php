<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_recommendations\Kernel;

use DG\BypassFinals;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\helfi_api_base\ApiClient\ApiClient;
use Drupal\helfi_api_base\ApiClient\ApiResponse;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_recommendations\RecommendationManager;
use Drupal\helfi_recommendations\TopicsManagerInterface;
use Drupal\helfi_recommendations\Entity\SuggestedTopics;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Prophecy\Argument;
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
    // https://github.com/elastic/elasticsearch-php/issues/1227.
    BypassFinals::enable();

    parent::setUp();
    $this->setActiveProject(Project::ETUSIVU, $this->environment);

    $user = $this->createUser();
    $this->container->get('current_user')->setAccount($user);
  }

  /**
   * Tests showRecommendations without suggested topics reference fields.
   *
   * @covers ::showRecommendations
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
   *
   * @covers ::showRecommendations
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
   *
   * @covers ::showRecommendations
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
   *
   * @covers ::getRecommendations
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

    $nodeTitle = $this->randomString();
    $nodeTitleSV = $this->randomString();
    $nodeResult = Node::create([
      'type' => 'test_node_bundle',
      'title' => $nodeTitle,
    ]);
    $translation = $nodeResult->toArray();
    $translation['title'] = $nodeTitleSV;
    $nodeResult->addTranslation('sv', $translation);
    $nodeResult->save();

    $recommendationManager = $this->getSut([
      'hits' => [
        'hits' => [
          [
            '_source' => [
              'parent_instance' => [Project::ETUSIVU],
              'parent_type' => ['node'],
              'parent_bundle' => ['test_node_bundle'],
              'parent_id' => [$nodeResult->id()],
            ],
          ],
        ],
      ],
    ]);

    // Test recommendations in Finnish.
    $recommendations = $recommendationManager->getRecommendations($nodeSource);
    $this->assertNotEmpty($recommendations);
    $this->assertEquals($nodeTitle, $recommendations[0]['title']);
    $this->assertArrayHasKey('url', $recommendations[0]);

    // Test recommendations in English.
    $recommendations = $recommendationManager->getRecommendations($nodeSource, 3, 'sv');
    $this->assertNotEmpty($recommendations);
    $this->assertEquals($nodeTitleSV, $recommendations[0]['title']);
    $this->assertArrayHasKey('url', $recommendations[0]);
  }

  /**
   * Tests getRecommendations with empty search results.
   *
   * @covers ::getRecommendations
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

    $recommendationManager = $this->getSut([
      'hits' => [
        'hits' => [],
      ],
    ]);

    // Test recommendations with empty results.
    $recommendations = $recommendationManager->getRecommendations($nodeSource);
    $this->assertEmpty($recommendations);
  }

  /**
   * Tests getRecommendations with external results.
   *
   * @covers ::getRecommendations
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

    $resultTitle = $this->randomString();
    $recommendationManager = $this->getSut(
      [
        'hits' => [
          'hits' => [
            [
              '_source' => [
                'parent_instance' => [Project::ASUMINEN],
                'parent_type' => ['node'],
                'parent_bundle' => ['test_node_bundle'],
                'parent_id' => ['123'],
              ],
            ],
          ],
        ],
      ],
      [
        'data' => [
          (object) [
            'attributes' => (object) [
              'title' => $resultTitle,
              'path' => (object) [
                'alias' => '/test-node',
              ],
            ],
          ],
        ],
      ],
    );

    $recommendations = $recommendationManager->getRecommendations($nodeSource);
    $this->assertNotEmpty($recommendations);
    $this->assertEquals($resultTitle, $recommendations[0]['title']);
    $this->assertArrayHasKey('url', $recommendations[0]);
  }

  /**
   * Gets service under test.
   *
   * @param array $elasticData
   *   The elasticsearch mock data.
   * @param array $jsonApiData
   *   The json api data.
   *
   * @return \Drupal\helfi_recommendations\RecommendationManager
   *   The service under test.
   */
  private function getSut(
    array $elasticData = [],
    array $jsonApiData = [],
  ): RecommendationManager {
    $loggerChannel = $this->prophesize(LoggerChannelInterface::class);
    $entityTypeManager = $this->container->get(EntityTypeManagerInterface::class);
    $environmentResolver = $this->container->get(EnvironmentResolverInterface::class);
    $topicsManager = $this->container->get(TopicsManagerInterface::class);

    $jsonApiResponse = (object) $jsonApiData;
    $jsonApiClient = $this->prophesize(ApiClient::class);
    $jsonApiClient->makeRequest(Argument::any(), Argument::any())->willReturn(new ApiResponse($jsonApiResponse));

    $elasticResponse = $this->prophesize(Elasticsearch::class);
    $elasticResponse->asArray()->willReturn($elasticData);

    $elasticsearchClient = $this->prophesize(Client::class);
    $elasticsearchClient->search(Argument::any())->willReturn($elasticResponse->reveal());

    return new RecommendationManager(
      $loggerChannel->reveal(),
      $entityTypeManager,
      $environmentResolver,
      $topicsManager,
      $jsonApiClient->reveal(),
      $elasticsearchClient->reveal(),
    );
  }

}
