<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_recommendations\Kernel\Controller;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Url;
use Drupal\helfi_api_base\Cache\CacheTagInvalidator;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_recommendations\Entity\SuggestedTopics;
use Drupal\helfi_recommendations\RecommendationManager;
use Drupal\helfi_recommendations\RecommendationManagerInterface;
use Drupal\helfi_recommendations\TopicsManagerInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\helfi_platform_config\Traits\ElasticTrait;
use Drupal\Tests\helfi_recommendations\Kernel\AnnifKernelTestBase;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\node\Entity\NodeType;
use Elastic\Elasticsearch\ClientBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests the HtmxController.
 */
#[Group('helfi_recommendations')]
#[RunTestsInSeparateProcesses]
class HtmxControllerTest extends AnnifKernelTestBase {

  use ApiTestTrait;
  use NodeCreationTrait;
  use ElasticTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
  ];

  /**
   * The nodes.
   *
   * @var array<string,\Drupal\node\NodeInterface>
   */
  protected array $nodes = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    NodeType::create(['type' => 'article'])->save();

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

    $this->nodes['article'] = $this->createNode(['type' => 'article', 'status' => 1]);
    $this->nodes['test_node_bundle'] = $this->createNode([
      'type' => 'test_node_bundle',
      'title' => 'News en',
      'status' => 1,
      'test_keywords' => SuggestedTopics::create([
        'keywords' => [
          ['entity' => $term1, 'score' => 0.8],
          ['entity' => $term2, 'score' => 0.2],
        ],
      ]),
    ]);

    $this->nodes['test_node_bundle']
      ->addTranslation('sv', ['title' => 'News sv'] + $this->nodes['test_node_bundle']->toArray());

    $this->nodes['test_node_bundle']->save();

    // Create user to make sure we don't accidentally get UID 1 user
    // with all permissions.
    $this->createUser();

    $account = $this->createUser(permissions: [
      'access content',
    ]);
    $this->setCurrentUser($account);
  }

  /**
   * Gets the entity type.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity type.
   *
   * @return string
   *   The url.
   */
  private function getUri(EntityInterface $entity): string {
    $entityTypeId = $entity->getEntityTypeId();

    return (new Url('helfi_recommendations.' . $entityTypeId . '.htmx', [
      $entityTypeId => $entity->id(),
    ]))->toString();
  }

  /**
   * Tests the controller access.
   */
  #[Test]
  public function testAccess(): void {
    // Test support and unsupported node bundle.
    foreach (['article' => 403, 'test_node_bundle' => 200] as $type => $statusCode) {
      $request = $this->getMockedRequest($this->getUri($this->nodes[$type]));
      $response = $this->processRequest($request);
      $this->assertEquals($statusCode, $response->getStatusCode());
    }
  }

  /**
   * Tests the controller with mocked recommendations.
   */
  #[Test]
  public function testRecommendations(): void {
    $node = $this->nodes['test_node_bundle'];
    $svNode = $this->nodes['test_node_bundle']->getTranslation('sv');

    $elasticResponse = $this->createElasticsearchResponse([
      'hits' => [
        'hits' => [
          [
            '_score' => 1.0,
            '_source' => [
              'parent_instance' => [Project::ETUSIVU],
              'parent_type' => ['node'],
              'parent_bundle' => ['test_node_bundle'],
              'uuid' => [$this->randomString()],
              'parent_url_en' => [$node->toUrl('canonical', ['absolute' => TRUE])->toString()],
              'parent_url_sv' => [$svNode->toUrl('canonical', ['absolute' => TRUE])->toString()],
              'parent_title_en' => [$node->getTitle()],
              'parent_title_sv' => [$svNode->getTitle()],
            ],
          ],
        ],
      ],
    ]);
    $mock = $this->createMockHttpClient([$elasticResponse, $elasticResponse]);
    $client = ClientBuilder::create()
      ->setHttpClient($mock)
      ->build();

    $this->container->get('kernel')->rebuildContainer();
    $manager = new RecommendationManager(
      $this->prophesize(LoggerChannelInterface::class)->reveal(),
      $this->container->get(EnvironmentResolverInterface::class),
      $this->container->get(TopicsManagerInterface::class),
      $client,
      $this->container->get(CacheTagInvalidator::class),
      $this->container->get('state'),
    );
    $this->container->set(RecommendationManagerInterface::class, $manager);

    foreach (['en', 'sv'] as $langcode) {
      $this->setOverrideLanguageCode($langcode);
      $request = $this->getMockedRequest($this->getUri($node));
      $response = $this->processRequest($request);
      $this->assertEquals(200, $response->getStatusCode());
      $this->assertStringContainsString('News ' . $langcode, (string) $response->getContent());
      $this->assertStringNotContainsString('Search result score:', (string) $response->getContent());
    }

    $account = $this->createUser([
      'access content',
      'view recommendation score',
    ]);
    $this->setCurrentUser($account);

    foreach (['en', 'sv'] as $langcode) {
      $this->setOverrideLanguageCode($langcode);
      $request = $this->getMockedRequest($this->getUri($node));
      $response = $this->processRequest($request);
      $this->assertEquals(200, $response->getStatusCode());
      $this->assertStringContainsString('News ' . $langcode, (string) $response->getContent());
      $this->assertStringContainsString('Search result score:', (string) $response->getContent());
    }
  }

}
