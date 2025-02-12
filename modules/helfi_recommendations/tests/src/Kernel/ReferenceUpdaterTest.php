<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_recommendations\Kernel;

use Drupal\helfi_recommendations\Entity\SuggestedTopicsInterface;
use Drupal\helfi_recommendations\ReferenceUpdater;
use Drupal\node\Entity\Node;
use Drupal\Tests\helfi_recommendations\Traits\AnnifApiTestTrait;

/**
 * Tests reference updater.
 *
 * @group helfi_recommendations
 */
class ReferenceUpdaterTest extends AnnifKernelTestBase {

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
  public function testReferenceUpdater(): void {
    $referenceUpdater = $this->container->get(ReferenceUpdater::class);
    $expectedReferenceFields = [
      'node' => [
        'test_node_bundle' => ['test_keywords'],
      ],
    ];

    $this->assertEquals($expectedReferenceFields, $referenceUpdater->getAllReferenceFields());
    $this->assertEquals(['test_keywords'], $referenceUpdater->getReferenceFields('node', 'test_node_bundle'));
    $this->assertEquals([], $referenceUpdater->getReferenceFields('node', 'does-not-exists'));

    $node = Node::create([
      'type' => 'test_node_bundle',
      'title' => $this->randomString(),
      'test_keywords' => NULL,
    ]);

    $node->save();
    $orphans = $referenceUpdater->getReferencesWithoutTarget();

    $this->assertNotEmpty($orphans);
    foreach ($orphans as $orphan) {
      $this->assertEquals(['entity_type' => $node->getEntityTypeId(), 'id' => $node->id()], $orphan);
    }

    // Updating entity reference fields should
    // give test_keywords field a referenced entity.
    $this->assertEmpty(Node::load($node->id())->get('test_keywords')->entity);
    $referenceUpdater->updateEntityReferenceFields($node);
    $this->assertInstanceOf(SuggestedTopicsInterface::class, Node::load($node->id())->get('test_keywords')->entity);
  }

}
