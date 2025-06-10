<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_recommendations\Unit;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\helfi_recommendations\RecommendationManagerInterface;
use Drupal\helfi_recommendations\RecommendationsLazyBuilder;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Unit tests for RecommendationsLazyBuilder.
 *
 * @group helfi_recommendations
 * @coversDefaultClass \Drupal\helfi_recommendations\RecommendationsLazyBuilder
 */
class RecommendationsLazyBuilderTest extends UnitTestCase {
  use ProphecyTrait;

  /**
   * The recommendations lazy builder.
   *
   * @var \Drupal\helfi_recommendations\RecommendationsLazyBuilder
   */
  protected RecommendationsLazyBuilder $recommendationsLazyBuilder;

  /**
   * The mocked recommendation manager.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $recommendationManager;

  /**
   * The mocked entity type manager.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $entityTypeManager;

  /**
   * The mocked logger.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $logger;

  /**
   * The mocked entity storage.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $entityStorage;

  /**
   * The mocked entity.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $entity;

  /**
   * The mocked language.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $language;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->recommendationManager = $this->prophesize(RecommendationManagerInterface::class);
    $this->recommendationManager->getCacheTagForAll()->willReturn('test_cache_tag_all');
    $this->recommendationManager->getCacheTagForUuid('test_uuid_1')->willReturn('test_cache_tag_uuid_1');
    $this->recommendationManager->getCacheTagForUuid('test_uuid_2')->willReturn('test_cache_tag_uuid_2');
    $this->entityStorage = $this->prophesize(EntityStorageInterface::class);
    $this->entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $this->entityTypeManager->getStorage(Argument::any())->willReturn($this->entityStorage->reveal());
    $this->logger = $this->prophesize(LoggerInterface::class);
    $this->language = $this->prophesize(LanguageInterface::class);
    $this->entity = $this->prophesize(ContentEntityInterface::class);
    $this->entity->language()->willReturn($this->language->reveal());
    $this->entity->getCacheTags()->willReturn(['test_cache_tag_entity']);
    $this->entity->bundle()->willReturn('news_item');

    $this->recommendationsLazyBuilder = new RecommendationsLazyBuilder(
      $this->recommendationManager->reveal(),
      $this->entityTypeManager->reveal(),
      $this->logger->reveal(),
    );
    $this->recommendationsLazyBuilder->setStringTranslation($this->getStringTranslationStub());
  }

  /**
   * Tests the build method with a non compatible entity type.
   *
   * @covers ::build
   */
  public function testBuild(): void {
    $this->entityStorage->load(Argument::any())->willReturn(NULL);

    $result = $this->recommendationsLazyBuilder->build(TRUE, 'node', '1', 'en');
    $this->assertEquals([], $result);
  }

  /**
   * Tests the build method with a non-existing translation language.
   *
   * @covers ::build
   */
  public function testBuildWithNonExistingTranslationLanguage(): void {
    $this->entityStorage->load(Argument::any())->willReturn($this->entity->reveal());
    $this->language->getId()->willReturn('fi');
    $this->entity->hasTranslation(Argument::any())->willReturn(FALSE);

    $result = $this->recommendationsLazyBuilder->build(TRUE, 'node', '1', 'en');
    $this->assertEquals([], $result);
  }

  /**
   * Tests the build method with no recommendations and anonymous user.
   *
   * @covers ::build
   */
  public function testBuildWithNoRecommendationsAndAnonymousUser(): void {
    $this->entityStorage->load(Argument::any())->willReturn($this->entity->reveal());
    $this->language->getId()->willReturn('en');
    $this->recommendationManager->getRecommendations(Argument::any(), Argument::any(), Argument::any(), Argument::any())->willReturn([]);

    $result = $this->recommendationsLazyBuilder->build(TRUE, 'node', '1', 'en');
    $this->assertArrayHasKey('#cache', $result);
    $this->assertEquals(['test_cache_tag_entity', 'test_cache_tag_all'], $result['#cache']['tags']);
    $this->assertEquals(['languages:language_content', 'user.roles', 'url.path'], $result['#cache']['contexts']);
    $this->assertArrayNotHasKey('#no_results_message', $result);
  }

  /**
   * Tests the build method with no recommendations and authenticated user.
   *
   * @covers ::build
   */
  public function testBuildWithNoRecommendationsAndAuthenticatedUser(): void {
    $this->entityStorage->load(Argument::any())->willReturn($this->entity->reveal());
    $this->language->getId()->willReturn('en');
    $this->recommendationManager->getRecommendations(Argument::any(), Argument::any(), Argument::any(), Argument::any())->willReturn([]);

    $result = $this->recommendationsLazyBuilder->build(FALSE, 'node', '1', 'en');
    $this->assertArrayHasKey('#cache', $result);
    $this->assertArrayHasKey('#no_results_message', $result);
  }

  /**
   * Tests the build method with recommendations.
   *
   * @covers ::build
   * @covers ::getRecommendations
   */
  public function testBuildWithRecommendations(): void {
    $this->entityStorage->load(Argument::any())->willReturn($this->entity->reveal());
    $this->language->getId()->willReturn('en');
    $this->recommendationManager->getRecommendations(Argument::any(), Argument::any(), Argument::any(), Argument::any())->willReturn([
      ['uuid' => 'test_uuid_1'],
      ['uuid' => 'test_uuid_2'],
    ]);

    $result = $this->recommendationsLazyBuilder->build(TRUE, 'node', '1', 'en');
    $this->assertArrayHasKey('#cache', $result);
    $this->assertArrayHasKey('#rows', $result);
    $this->assertEquals([
      'test_cache_tag_entity',
      'test_cache_tag_all',
      'test_cache_tag_uuid_1',
      'test_cache_tag_uuid_2',
    ], $result['#cache']['tags']);
    $this->assertEquals([
      ['uuid' => 'test_uuid_1'],
      ['uuid' => 'test_uuid_2'],
    ], $result['#rows']);
    $this->assertArrayNotHasKey('#no_results_message', $result);
  }

  /**
   * Tests the build method with recommendations and translation.
   *
   * @covers ::build
   * @covers ::getRecommendations
   */
  public function testBuildWithRecommendationsAndTranslation(): void {
    $this->entityStorage->load(Argument::any())->willReturn($this->entity->reveal());
    $this->language->getId()->willReturn('en');
    $this->entity->hasTranslation('fi')->willReturn(TRUE);
    $this->entity->getTranslation('fi')->willReturn($this->entity->reveal());
    $this->recommendationManager->getRecommendations(Argument::any(), Argument::any(), Argument::any(), Argument::any())
      ->shouldBeCalled()
      ->willReturn([]);

    $this->recommendationsLazyBuilder->build(TRUE, 'node', '1', 'fi');
  }

}
