<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_recommendations\Unit;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_recommendations\RecommendationManager;
use Drupal\helfi_recommendations\TopicsManagerInterface;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;
use Elastic\Elasticsearch\ClientBuilder;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Unit tests for RecommendationManager.
 *
 * @group helfi_recommendations
 * @coversDefaultClass \Drupal\helfi_recommendations\RecommendationManager
 */
class RecommendationManagerTest extends UnitTestCase {
  use ProphecyTrait;

  /**
   * The recommendation manager.
   *
   * @var \Drupal\helfi_recommendations\RecommendationManager
   */
  protected RecommendationManager $recommendationManager;

  /**
   * The mocked logger.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $logger;

  /**
   * The mocked entity type manager.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $entityTypeManager;

  /**
   * The mocked environment resolver.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $environmentResolver;

  /**
   * The mocked topics manager.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $topicsManager;

  /**
   * The mocked JSON API client.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $jsonApiClient;

  /**
   * The Elasticsearch client.
   *
   * @var \Elastic\Elasticsearch\Client
   */
  protected $elasticClient;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->logger = $this->prophesize(LoggerInterface::class);
    $this->entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $this->environmentResolver = $this->prophesize(EnvironmentResolverInterface::class);
    $this->topicsManager = $this->prophesize(TopicsManagerInterface::class);
    $this->elasticClient = ClientBuilder::create()->build();

    $this->recommendationManager = new RecommendationManager(
      $this->logger->reveal(),
      $this->entityTypeManager->reveal(),
      $this->environmentResolver->reveal(),
      $this->topicsManager->reveal(),
      $this->elasticClient
    );
  }

  /**
   * Tests the showRecommendations method with no suggested topics fields.
   *
   * @covers ::showRecommendations
   */
  public function testShowRecommendationsNoFields(): void {
    $entity = $this->prophesize(ContentEntityInterface::class);
    $entity->getFieldDefinitions()->willReturn([]);

    $this->assertFalse($this->recommendationManager->showRecommendations($entity->reveal()));
  }

  /**
   * Tests the showRecommendations method with suggested topics fields.
   *
   * @covers ::showRecommendations
   */
  public function testShowRecommendationsWithFields(): void {
    $entity = $this->prophesize(ContentEntityInterface::class);
    $field_definition = $this->prophesize(FieldDefinitionInterface::class);
    $field_definition->getType()->willReturn('suggested_topics_reference');

    $field = $this->prophesize(EntityReferenceFieldItemListInterface::class);
    $field->getValue()->willReturn([
      ['show_block' => TRUE],
    ]);

    $entity->getFieldDefinitions()->willReturn([
      'field_suggested_topics' => $field_definition->reveal(),
    ]);
    $entity->get('field_suggested_topics')->willReturn($field->reveal());

    $this->assertTrue($this->recommendationManager->showRecommendations($entity->reveal()));
  }

  /**
   * Tests the getRecommendations method with no keywords.
   *
   * @covers ::getRecommendations
   */
  public function testGetRecommendationsNoKeywords(): void {
    $entity = $this->prophesize(ContentEntityInterface::class);
    $language = $this->prophesize(LanguageInterface::class);
    $language->getId()->willReturn('en');
    $entity->hasTranslation('en')->willReturn(TRUE);
    $entity->language()->willReturn($language->reveal());
    $entity->id()->willReturn(1);

    $this->topicsManager->getKeywords($entity->reveal())->willReturn([]);

    $this->assertEquals(
      [],
      $this->recommendationManager->getRecommendations($entity->reveal())
    );
  }

  /**
   * Tests error handling in getRecommendations.
   *
   * @covers ::getRecommendations
   */
  public function testGetRecommendationsErrorHandling(): void {
    // Create a mock that implements both interfaces.
    $entity = $this->prophesize(ContentEntityInterface::class)
      ->willImplement(TranslatableInterface::class);

    $language = $this->prophesize(LanguageInterface::class);
    $language->getId()->willReturn('en');
    $entity->language()->willReturn($language->reveal());
    $entity->hasTranslation('en')->willReturn(TRUE);
    $entity->getTranslation('en')->willReturn($entity->reveal());
    $entity->id()->willReturn(1);
    $this->topicsManager->getKeywords($entity->reveal())->willThrow(new \Exception('Test error'));
    $this->logger->log('error', Argument::any(), Argument::any())->shouldBeCalled();

    $this->assertEquals(
      [],
      $this->recommendationManager->getRecommendations($entity->reveal())
    );
  }

}
