<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\ConfigUpdate;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\helfi_platform_config\Kernel\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the config rewriter.
 */
#[Group('helfi_platform_config')]
#[RunTestsInSeparateProcesses]
class ConfigRewriterTest extends KernelTestBase {

  private const string DISPLAY = 'core.entity_view_display.entity_test.entity_test.default';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'entity_test',
    'helfi_platform_config_rewrite_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test');

    foreach (['field_test_a', 'field_test_b'] as $name) {
      FieldStorageConfig::create([
        'field_name' => $name,
        'entity_type' => 'entity_test',
        'type' => 'string',
      ])->save();
      FieldConfig::create([
        'field_name' => $name,
        'entity_type' => 'entity_test',
        'bundle' => 'entity_test',
      ])->save();
    }
    EntityViewDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
      'status' => TRUE,
    ])
      ->setComponent('field_test_b', ['type' => 'string'])
      ->save();
  }

  /**
   * Tests that rewritten configuration entities are saved like on import.
   */
  public function testConfigEntityIsSaved(): void {
    $storage = $this->container->get('config.storage');

    $this->container->get('config_rewrite.config_rewriter')
      ->rewriteModuleConfig('helfi_platform_config_rewrite_test');

    $rewritten = $storage->read(self::DISPLAY);
    $this->assertIsArray($rewritten);
    // The display sorts its components on save.
    $this->assertSame(['field_test_a', 'field_test_b'], array_keys($rewritten['content']));

    // Importing the rewritten configuration doesn't change it.
    $entityStorage = $this->container->get('entity_type.manager')->getStorage('entity_view_display');
    $entityStorage->resetCache();
    $display = $entityStorage->loadOverrideFree('entity_test.entity_test.default');
    $this->assertInstanceOf(ConfigEntityInterface::class, $display);
    $entityStorage->updateFromStorageRecord($display, $rewritten)
      ->setSyncing(TRUE)
      ->save();
    $this->assertSame($rewritten, $storage->read(self::DISPLAY));
  }

}
