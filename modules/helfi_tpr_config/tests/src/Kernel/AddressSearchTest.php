<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_tpr_config\Kernel;

use Drupal\helfi_tpr_config\Plugin\views\filter\AddressSearch;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests AddressSearch override.
 */
class AddressSearchTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_address_search',
    'helfi_tpr_config',
    'views',
  ];

  /**
   * Tests that address search is overridden.
   */
  public function testAddressSearch(): void {
    $pluginManager = $this->container->get('plugin.manager.views.filter');
    $instance = $pluginManager->createInstance('address_search');

    // Asserts that the plugin is overridden.
    $this->assertInstanceOf(AddressSearch::class, $instance);
  }

}
