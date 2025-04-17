<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_recommendations\Kernel;

use Drupal\helfi_recommendations\Plugin\QueueWorker\QueueWorker;
use Drupal\helfi_recommendations\TopicsManagerInterface;
use Drupal\node\Entity\Node;
use Drupal\Tests\helfi_recommendations\Traits\AnnifApiTestTrait;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests TopicsManager.
 *
 * @group helfi_recommendations
 */
class QueueWorkerTest extends AnnifKernelTestBase {

  use AnnifApiTestTrait;
  use ProphecyTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
  ];

  /**
   * Tests queue worker.
   */
  public function testQueueWorker(): void {
    $topicsManager = $this->prophesize(TopicsManagerInterface::class);
    $topicsManager
      ->processEntity(Argument::any(), TRUE, FALSE, TRUE)
      ->shouldBeCalled();

    $node = Node::create([
      'title' => $this->randomMachineName(),
      'type' => 'test_node_bundle',
    ]);
    $node->save();

    $this->container->set(TopicsManagerInterface::class, $topicsManager->reveal());

    $worker = $this->container
      ->get('plugin.manager.queue_worker')
      ->createInstance('helfi_recommendations_queue');

    $this->assertInstanceOf(QueueWorker::class, $worker);

    $worker->processItem([
      'entity_id' => $node->id(),
      'entity_type' => $node->getEntityTypeId(),
      'language' => $node->language()->getId(),
      'overwrite' => TRUE,
    ]);
  }

}
