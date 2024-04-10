<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\KernelTests\KernelTestBase;

/**
 * Scans bundled configuration.
 *
 * @group helfi_platform_config
 */
class ConfigurationTest extends KernelTestBase {

  /**
   * The extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  private ModuleExtensionList $extensionList;

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $this->extensionList = $this->container->get('extension.list.module');
  }

  /**
   * Makes sure configuration does not contain '_core' key.
   */
  public function testModuleCoreKey() : void {
    $folder = $this->extensionList->getPath('helfi_platform_config');
    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($folder)
    );
    $yamlIterator = new \RegexIterator($iterator, '/.+\.yml$/i', \RegexIterator::GET_MATCH);

    foreach ($yamlIterator as $item) {
      if (!is_array($item)) {
        continue;
      }
      $file = reset($item);

      if (!str_contains($file, '/config/')) {
        continue;
      }
      $content = Yaml::decode(file_get_contents($file));
      $this->assertArrayNotHasKey('_core', $content, "[{$file}] has _core key");
    }
  }

}
