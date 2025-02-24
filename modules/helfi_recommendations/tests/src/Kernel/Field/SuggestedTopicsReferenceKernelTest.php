<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_recommendations\Kernel\Field;

use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\helfi_recommendations\Entity\SuggestedTopics;
use Drupal\helfi_recommendations\Plugin\Field\FieldType\SuggestedTopicsReferenceItem;
use Drupal\node\Entity\Node;
use Drupal\Tests\helfi_recommendations\Kernel\AnnifKernelTestBase;

/**
 * Tests suggested topics entity.
 *
 * @group helfi_recommendations
 */
class SuggestedTopicsReferenceKernelTest extends AnnifKernelTestBase {

  /**
   * {@inheritDoc}
   */
  protected static $modules = [
    'node',
  ];

  /**
   * Tests suggested topics reference item.
   */
  public function testPublishProperty(): void {
    $node = Node::create([
      'type' => 'test_node_bundle',
      'title' => $this->randomString(),
      'test_keywords' => SuggestedTopics::create(),
    ]);

    // Field widget uses computed property to update referenced entity values.
    // This test ensures that changed to the $published property are copied to
    // the entity.
    foreach ($node->get('test_keywords') as $field) {
      $this->assertInstanceOf(SuggestedTopicsReferenceItem::class, $field);
      $entity = $field->entity;
      $this->assertInstanceOf(EntityPublishedInterface::class, $entity);

      $field->published = FALSE;
      $this->assertFalse($entity->isPublished());

      $field->published = TRUE;
      $this->assertTrue($entity->isPublished());

      $node->setUnpublished();
      $node->save();

      $this->assertFalse(SuggestedTopics::load($entity->id())->isPublished());
    }
  }

}
