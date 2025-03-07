<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\Block;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\helfi_platform_config\EntityVersionMatcher;
use Drupal\helfi_platform_config\Plugin\Block\ContentBlockBase;

/**
 * @coversDefaultClass \Drupal\helfi_platform_config\Plugin\Block\ContentBlockBase
 *
 * @group helfi_platform_config
 */
class ContentBlockTest extends BlockUnitTestBase {

  /**
   * The content block.
   *
   * @var \Drupal\helfi_platform_config\Plugin\Block\ContentBlockBase
   */
  protected ContentBlockBase $contentBlock;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->contentBlock = new class (
      [],
      'content_block',
      ['provider' => 'helfi_platform_config'],
      $this->entityTypeManager,
      $this->entityVersionMatcher,
      $this->moduleHandler
    ) extends ContentBlockBase {
    };
  }

  /**
   * Tests that getCacheTags() returns an empty array when no entity is present.
   *
   * @covers ::getCacheTags
   */
  public function testGetCacheTagsReturnsParentCacheTagsWhenNoEntity(): void {
    $this->entityVersionMatcher->expects($this->once())
      ->method('getType')
      ->willReturn(['entity' => NULL, 'entity_version' => NULL]);

    $this->assertSame([], $this->contentBlock->getCacheTags());
  }

  /**
   * Tests that getCacheTags() correctly merges entity cache tags.
   *
   * @covers ::getCacheTags
   */
  public function testGetCacheTagsReturnsMergedCacheTags(): void {
    $entity = $this->createMock(EntityInterface::class);
    $entity->expects($this->once())
      ->method('getCacheTags')
      ->willReturn(['entity:node:1']);

    $this->entityVersionMatcher->expects($this->once())
      ->method('getType')
      ->willReturn([
        'entity' => $entity,
        'entity_version' => EntityVersionMatcher::ENTITY_VERSION_CANONICAL,
      ]);

    $expectedTags = Cache::mergeTags([], ['entity:node:1']);

    $this->assertSame($expectedTags, $this->contentBlock->getCacheTags());
  }

  /**
   * Tests that getCacheContexts() returns the expected cache contexts.
   *
   * @covers ::getCacheContexts
   */
  public function testGetCacheContexts(): void {
    $this->assertSame(['route'], $this->contentBlock->getCacheContexts());
  }

  /**
   * Tests that build() returns an empty array.
   *
   * @covers ::build
   */
  public function testBuildReturnsEmptyArray(): void {
    $this->assertSame([], $this->contentBlock->build());
  }

  /**
   * Tests that getCurrentEntityVersion() returns the correct matcher type.
   *
   * @covers ::getCurrentEntityVersion
   */
  public function testGetCurrentEntityVersionReturnsMatcherType(): void {
    $expectedVersion = ['entity' => NULL, 'entity_version' => EntityVersionMatcher::ENTITY_VERSION_CANONICAL];

    $this->entityVersionMatcher->expects($this->once())
      ->method('getType')
      ->willReturn($expectedVersion);

    $reflection = new \ReflectionClass($this->contentBlock);
    $method = $reflection->getMethod('getCurrentEntityVersion');

    $result = $method->invoke($this->contentBlock);

    $this->assertSame($expectedVersion, $result);
  }

}
