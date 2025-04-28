<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_recommendations\Kernel\Plugin\Block;

use Drupal\helfi_platform_config\EntityVersionMatcher;
use Drupal\helfi_recommendations\Plugin\Block\RecommendationsBlock;
use Drupal\node\Entity\Node;
use Drupal\Tests\helfi_recommendations\Kernel\AnnifKernelTestBase;

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
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->entityVersionMatcher = $this->prophesize(EntityVersionMatcher::class);
    $this->entityVersionMatcher->getType()->willReturn(['entity' => NULL]);
    $this->container->set(EntityVersionMatcher::class, $this->entityVersionMatcher->reveal());
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

    $block = RecommendationsBlock::create($this->container, [], 'helfi_recommendations', ['provider' => 'helfi_recommendations']);
    $result = $block->build();
    $this->assertEmpty($result);
  }

}
