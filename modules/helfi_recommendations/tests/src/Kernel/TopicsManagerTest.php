<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_recommendations\Kernel;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\helfi_recommendations\Client\ApiClient;
use Drupal\helfi_recommendations\Entity\SuggestedTopics;
use Drupal\helfi_recommendations\Entity\SuggestedTopicsInterface;
use Drupal\helfi_recommendations\TextConverter\TextConverterInterface;
use Drupal\helfi_recommendations\TopicsManager;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\helfi_recommendations\Traits\AnnifApiTestTrait;
use GuzzleHttp\Psr7\Response;
use Prophecy\Argument;

/**
 * Tests TopicsManager.
 *
 * @group helfi_recommendations
 */
class TopicsManagerTest extends AnnifKernelTestBase {

  use AnnifApiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
  ];

  /**
   * Tests entities without keyword field.
   */
  public function testUnsupportedEntity(): void {
    NodeType::create([
      'name' => $this->randomMachineName(),
      'type' => 'some_other_node_bundle',
    ])->save();

    $node = Node::create([
      'type' => 'some_other_node_bundle',
      'title' => $this->randomString(),
    ]);

    // hasField(TopicsManager::KEYWORD_FIELD) for entity is FALSE.
    $queue = $this->prophesize(QueueInterface::class);
    $queue
      ->createItem(Argument::any())
      ->shouldNotBeCalled();

    $sut = $this->getSut(queue: $queue->reveal());

    $sut->queueEntity($node);
    $sut->processEntity($node);
    $sut->processEntities([$node]);
  }

  /**
   * Tests queue.
   */
  public function testQueueDeduplication(): void {
    $node = Node::create([
      'type' => 'test_node_bundle',
      'title' => $this->randomString(),
      'test_keywords' => SuggestedTopics::create(),
    ]);

    $queue = $this->prophesize(QueueInterface::class);
    $queue
      ->createItem(Argument::any())
      ->shouldNotBeCalled();

    $sut = $this->getSut(
      responses: [$this->getMockResponse('suggest.json', [$node])],
      queue: $queue->reveal()
    );

    $sut->processEntity($node);
    $sut->queueEntity($node);
  }

  /**
   * Tests batch with multiple languages.
   */
  public function testBatch(): void {
    $langcodes = ['foo' => 'fi', 'foobar' => 'xzz', 'bar' => 'sv'];
    $batch = array_map(fn ($langcode) => Node::create([
      'type' => 'test_node_bundle',
      'title' => $this->randomString(),
      'test_keywords' => SuggestedTopics::create(),
      'langcode' => $langcode,
    ]), $langcodes);

    $sut = $this->getSut(responses: [
      new Response(200, [], json_encode([
        json_decode($this->getFixture('suggest.json'), TRUE) + [
          'document_id' => 'foo',
        ],
      ])),
      new Response(200, [], json_encode([
        json_decode($this->getFixture('suggest.json'), TRUE) + [
          'document_id' => 'bar',
        ],
      ])),
    ]);

    $sut->processEntities($batch);

    $supported = ['fi', 'sv', 'en'];
    foreach ($batch as $key => $node) {
      $reference = $node->get('test_keywords')->entity;

      $this->assertInstanceOf(SuggestedTopicsInterface::class, $reference);

      // Referenced entity should get keywords if it is in supported language.
      $this->assertEquals(in_array($langcodes[$key], $supported), $reference->hasKeywords());
    }
  }

  /**
   * Tests parent translations field is set correctly.
   */
  public function testParentTranslationsField(): void {
    $topic = SuggestedTopics::create();
    $node = Node::create([
      'type' => 'test_node_bundle',
      'title' => $this->randomString(),
      'test_keywords' => $topic,
      'langcode' => 'fi',
    ]);
    $node->addTranslation('sv', $node->toArray());
    $node->addTranslation('en', ['status' => [['value' => 0]]] + $node->toArray());

    $sut = $this->getSut();
    $sut->queueEntity($node, TRUE);

    // Only published translations should be included.
    $this->assertEquals([['value' => 'fi'], ['value' => 'sv']], $topic->get('parent_translations')->getValue());
  }

  /**
   * Gets service under test.
   */
  private function getSut(
    array $responses = [],
    ?TextConverterInterface $textConverter = NULL,
    ?QueueInterface $queue = NULL,
  ): TopicsManager {
    $textConverterManager = $this->getTextConverterManager($textConverter);

    $client = new ApiClient(
      $this->createMockHttpClient($responses),
      $textConverterManager,
    );

    $entityTypeManager = $this->container->get(EntityTypeManagerInterface::class);

    if (!$queue) {
      $queue = $this
        ->prophesize(QueueInterface::class)
        ->reveal();
    }

    $queueFactory = $this->prophesize(QueueFactory::class);
    $queueFactory
      ->get(Argument::any())
      ->willReturn($queue);

    return new TopicsManager(
      $entityTypeManager,
      $client,
      $queueFactory->reveal(),
    );
  }

}
