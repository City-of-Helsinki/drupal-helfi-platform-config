<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\Helper;

use Drupal\Core\Config\ConfigException;
use Drupal\helfi_platform_config\Helper\BlockInstaller;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the BlockInstaller helper class.
 *
 * Tests the block installation functionality with the
 * following test cases:
 * - Successful block installation with valid configuration
 * - Exception thrown for missing required theme variation
 * - Exception thrown for missing required block configuration.
 *
 * @coversDefaultClass \Drupal\helfi_platform_config\Helper\BlockInstaller
 * @group helfi_platform_config
 */
class BlockInstallerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_platform_config',
    'helfi_api_base',
    'config_rewrite',
    'block',
    'system',
    'user',
    'language',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->container->get('theme_installer')->install(['stark']);
  }

  /**
   * Tests successful block installation with valid configuration.
   *
   * @covers ::install
   */
  public function testInstallBlockWithValidConfig(): void {
    $block = [
      'id' => 'test_block',
      'plugin' => 'system_main_block',
      'provider' => 'system',
      'settings' => [
        'label' => 'Test Block',
        'label_display' => TRUE,
      ],
    ];

    $variations = [
      [
        'theme' => 'stark',
        'region' => 'content',
      ],
    ];

    $installer = $this->container->get(BlockInstaller::class);
    $blockStorage = $this->container->get('entity_type.manager')->getStorage('block');

    $this->assertNull($blockStorage->load('test_block'));

    $installer->install($block, $variations);

    $installedBlock = $blockStorage->load('test_block');
    $this->assertNotNull($installedBlock);
    $this->assertEquals('test_block', $installedBlock->id());
    $this->assertEquals('stark', $installedBlock->getTheme());
    $this->assertEquals('content', $installedBlock->getRegion());
    $this->assertEquals('system_main_block', $installedBlock->getPluginId());
  }

  /**
   * Tests exception thrown for missing required block configuration.
   *
   * @covers ::install
   */
  public function testInstallThrowsExceptionForMissingRequiredConfig(): void {
    $block = [
      'plugin' => 'system_main_block',
      // Missing required 'id' and 'provider'.
    ];

    $variations = [
      [
        'theme' => 'stark',
        'region' => 'content',
      ],
    ];

    $installer = $this->container->get(BlockInstaller::class);

    $this->expectException(ConfigException::class);
    $this->expectExceptionMessage('Missing required "id" or "provider" block config.');

    $installer->install($block, $variations);
  }

  /**
   * Tests exception thrown for missing required theme variation.
   *
   * @covers ::install
   */
  public function testInstallThrowsExceptionForMissingRequiredVariation(): void {
    $block = [
      'id' => 'test_block',
      'plugin' => 'system_main_block',
      'provider' => 'system',
    ];

    $variations = [
      [
        'invalid' => 'value',
        // Missing required 'theme' and 'region'.
      ],
    ];

    $installer = $this->container->get(BlockInstaller::class);

    $this->expectException(ConfigException::class);
    $this->expectExceptionMessage('Missing required "theme" or "region" variation.');

    $installer->install($block, $variations);
  }

}
