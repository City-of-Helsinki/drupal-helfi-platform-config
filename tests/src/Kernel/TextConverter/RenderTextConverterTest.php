<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel;

use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\helfi_platform_config\TextConverter\RenderTextConverter;
use Drupal\helfi_platform_config\TextConverter\Strategy;
use Drupal\helfi_platform_config\TextConverter\TextConverterInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests reference updater.
 *
 * @group helfi_recommendations
 */
class RenderTextConverterTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_platform_config',
    'helfi_api_base',
    'config_rewrite',
    'node',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();

    NodeType::create([
      'name' => $this->randomMachineName(),
      'type' => 'test_node_bundle',
    ])->save();

    DateFormat::create([
      'id' => 'fallback',
      'pattern' => 'D, m/d/Y - H:i',
      'label' => 'Fallback',
    ])->save();
  }

  /**
   * Tests default text converter implementation.
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

    $this->assertFalse($renderTextConverter->applies($node, Strategy::Default));

    // Create text_converter view mode for nodes.
    EntityViewMode::create([
      'id' => 'node.text_converter',
      'targetEntityType' => 'node',
      'status' => TRUE,
      'label' => $this->randomMachineName(),
    ])->save();
    EntityViewDisplay::create([
      'id' => 'node.test_node_bundle.text_converter',
      'targetEntityType' => 'node',
      'bundle' => 'test_node_bundle',
      'mode' => 'text_converter',
      'status' => TRUE,
    ])->save();

    $this->assertTrue($renderTextConverter->applies($node, Strategy::Default));

    $this->assertStringContainsString($title, $renderTextConverter->convert($node, Strategy::Default));
  }

}
