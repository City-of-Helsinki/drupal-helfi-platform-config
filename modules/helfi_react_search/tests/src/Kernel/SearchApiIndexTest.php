<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_api_base\Kernel\Plugin\DebugDataItem;

use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Entity\Server;

/**
 * Tests SearchApiIndex debug data plugin.
 *
 * @group helfi_api_base
 */
class SearchApiIndexTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_api_base',
    'helfi_react_search',
    'search_api_db',
    'search_api',
    'system',
  ];

  /**
   * Tests that the plugin collects data properly.
   */
  public function testPlugin() : void {
    $this->installSchema('search_api', ['search_api_item']);
    $this->installEntitySchema('search_api_task');
    $this->installConfig('search_api');

    $server = Server::create([
      'id' => 'server',
      'name' => 'Server & Name',
      'status' => TRUE,
      'backend' => 'search_api_db',
      'backend_config' => [
        'min_chars' => 3,
        'database' => 'default:default',
      ],
    ]);
    $server->save();

    /** @var \Drupal\helfi_api_base\DebugDataItemPluginManager $manager */
    $manager = $this->container->get('plugin.manager.debug_data_item');
    /** @var \Drupal\helfi_api_base\Plugin\DebugDataItem\MaintenanceMode $plugin */
    $plugin = $manager->createInstance('search_api');

    $this->assertTrue($plugin->check());

    // Invalid backend.
    $server = Server::create([
      'id' => 'server_2',
      'name' => 'Server & Name',
      'status' => TRUE,
    ]);
    $server->save();

    $this->assertFalse($plugin->check());
  }

}
