<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_recommendations\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\helfi_recommendations\TextConverter\RenderTextConverter;
use Drupal\helfi_recommendations\TextConverter\TextConverterInterface;
use Drupal\node\Entity\Node;
use Drupal\Tests\helfi_recommendations\Traits\AnnifApiTestTrait;

/**
 * Tests reference updater.
 *
 * @group helfi_recommendations
 */
class RenderTextConverterTest extends AnnifKernelTestBase {

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
  public function testRenderTextConverter(): void {
    $title = $this->randomString();

    $node = Node::create([
      'type' => 'test_node_bundle',
      'title' => $title,
      'test_keywords' => NULL,
    ]);
    $node->save();

    $renderTextConverter = $this->container->get(RenderTextConverter::class);
    $this->assertInstanceOf(TextConverterInterface::class, $renderTextConverter);

    $this->assertFalse($renderTextConverter->applies($node));

    // Create text_converter view mode for nodes.
    EntityViewDisplay::create([
      'id' => 'node.test_node_bundle.text_converter',
      'targetEntityType' => 'node',
      'status' => TRUE,
      'bundle' => 'test_node_bundle',
      'mode' => 'text_converter',
    ])->save();

    $this->assertTrue($renderTextConverter->applies($node));

    $this->assertStringContainsString($title, $renderTextConverter->convert($node));
  }

}
