<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_recommendations\Kernel\Plugin\Block;

use Drupal\helfi_platform_config\EntityVersionMatcher;
use Drupal\helfi_recommendations\Plugin\Block\RecommendationsBlock;
use Drupal\helfi_recommendations\RecommendationManagerInterface;
use Drupal\node\Entity\Node;
use Drupal\Tests\helfi_recommendations\Kernel\AnnifKernelTestBase;
use Prophecy\Argument;

/**
 * Tests recommendations block.
 *
 * @group helfi_platform_config
 */
class RecommendationsBlockKernelTest extends AnnifKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'config_rewrite',
    'helfi_platform_config',
    'helfi_api_base',
    'node',
  ];

  /**
   * Mocked entity version matcher.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $entityVersionMatcher;

  /**
   * Mocked recommendation manager.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $recommendationManager;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->entityVersionMatcher = $this->prophesize(EntityVersionMatcher::class);
    $this->entityVersionMatcher->getType()->willReturn(['entity' => NULL]);
    $this->container->set('helfi_platform_config.entity_version_matcher', $this->entityVersionMatcher->reveal());

    $this->recommendationManager = $this->prophesize(RecommendationManagerInterface::class);
    $this->container->set(RecommendationManagerInterface::class, $this->recommendationManager->reveal());
  }

  /**
   * Test that build() returns an empty array for unknown entity type.
   */
  public function testNonContentEntity(): void {
    $block = RecommendationsBlock::create($this->container, [], 'helfi_recommendations', ['provider' => 'helfi_recommendations']);
    $result = $block->build();
    $this->assertEmpty($result);

    $cache_tags = $block->getCacheTags();
    $this->assertEmpty($cache_tags);
  }

  /**
   * Test that build returns an empty result when there are no recommendations.
   */
  public function testNoRecommendations(): void {
    $node = Node::create([
      'type' => 'test_node_bundle',
      'title' => 'Test node',
    ]);
    $node->save();

    $this->entityVersionMatcher->getType()->willReturn(['entity' => $node]);
    $this->recommendationManager->getRecommendations($node, Argument::any(), Argument::any(), Argument::any())->willReturn([]);

    $block = RecommendationsBlock::create($this->container, [], 'helfi_recommendations', ['provider' => 'helfi_recommendations']);
    $result = $block->build();
    $this->assertEmpty($result);
  }

  /**
   * Test cache contexts.
   */
  public function testCacheContexts(): void {
    $block = RecommendationsBlock::create($this->container, [], 'helfi_recommendations', ['provider' => 'helfi_recommendations']);
    $cache_contexts = $block->getCacheContexts();
    $this->assertEquals([
      'languages:language_content',
      'user.roles',
      'url.path',
    ], $cache_contexts);
  }

  /**
   * Test cache tags.
   */
  public function testCacheTags(): void {
    $block = RecommendationsBlock::create($this->container, [], 'helfi_recommendations', ['provider' => 'helfi_recommendations']);
    $cache_tags = $block->getCacheTags();
    $this->assertEquals([], $cache_tags);
  }

  /**
   * Test cache max age.
   */
  public function testCacheMaxAge(): void {
    $block = RecommendationsBlock::create($this->container, [], 'helfi_recommendations', ['provider' => 'helfi_recommendations']);
    $cache_max_age = $block->getCacheMaxAge();
    $this->assertEquals(3600, $cache_max_age);
  }

  /**
   * Test block access.
   */
  public function testBlockAccess(): void {
    $block = RecommendationsBlock::create($this->container, [], 'helfi_recommendations', ['provider' => 'helfi_recommendations']);
    $access = $block->access($this->createUser());
    $this->assertFalse($access);
  }

  /**
   * Test build with recommendations.
   */
  public function testBuildWithRecommendations(): void {
    $node = Node::create([
      'type' => 'test_node_bundle',
      'title' => $this->randomString(),
    ]);
    $node->save();

    $this->entityVersionMatcher->getType()->willReturn(['entity' => $node]);
    $this->recommendationManager->getRecommendations($node, Argument::any(), Argument::any(), Argument::any())->willReturn([
      [
        'uuid' => 'test-uuid',
        'url' => 'test-url',
        'title' => 'test-title',
        'score' => 0.8,
      ],
    ]);

    $block = RecommendationsBlock::create($this->container, [], 'helfi_recommendations', ['provider' => 'helfi_recommendations']);
    $result = $block->build();
    $this->assertArrayHasKey('#rows', $result);
  }

}
