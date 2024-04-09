<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\KernelTests\KernelTestBase;

/**
 * Make sure translated configuration is exported with UUIDs.
 *
 * @group helfi_platform_config
 */
class ConfigurationUuidTest extends KernelTestBase {

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
   * Asserts the matching file has a UUID.
   *
   * Finds the matching configuration file for given translation file.
   *
   * For example, '/config/install/language/fi/field.yml' file should have
   * a matching configuration file with the same name under '/config/install'
   * or '/config/optional' folder.
   *
   * @param string $module
   *   The module this file belongs to.
   * @param string $filename
   *   The language file.
   */
  private function assertMatchingFileHasUuid(string $module, string $filename) : void {
    $path = $this->extensionList->getPath($module);

    // The matching file can be under /optional or /install folder, make sure
    // to check both.
    $matchingFile = NULL;
    /** @var \DirectoryIterator $item */
    foreach (['optional', 'install'] as $type) {
      $file = sprintf('%s/config/%s/%s', $path, $type, $filename);

      if (!file_exists($file)) {
        continue;
      }
      $matchingFile = $file;
    }

    if (!$matchingFile) {
      throw new \InvalidArgumentException(
        sprintf('Failed to find a pair for: %s', $filename)
      );
    }

    $yaml = Yaml::decode(file_get_contents($matchingFile));
    $this->assertArrayHasKey('uuid', $yaml, "[{$matchingFile}] is missing UUID");
  }

  /**
   * Asserts that all module configuration contains 'uuid' key.
   *
   * @param string $module
   *   The module to check.
   */
  private function assertModuleUuids(string $module) : void {
    $path = $this->extensionList->getPath($module);

    foreach (['install', 'optional'] as $type) {
      $configPath = sprintf('%s/config/%s', $path, $type);

      if (!is_dir($configPath . '/language')) {
        continue;
      }

      foreach (['fi', 'sv'] as $language) {
        $languagePath = sprintf('%s/language/%s', $configPath, $language);

        if (!is_dir($languagePath)) {
          continue;
        }

        /** @var \DirectoryIterator $file */
        foreach (new \DirectoryIterator($languagePath) as $file) {
          if ($file->getExtension() !== 'yml') {
            continue;
          }
          $this->assertMatchingFileHasUuid($module, $file->getFilename());
        }
      }
    }
  }

  /**
   * Gets the submodules.
   *
   * @return array
   *   The submodules list.
   */
  private function getSubModules() : array {
    $path = $this->extensionList->getPath('helfi_platform_config');

    $modules = [];
    /** @var \DirectoryIterator $item */
    foreach (new \DirectoryIterator($path . '/modules') as $item) {
      if (!$item->isDir() || $item->isDot()) {
        continue;
      }
      $modules[] = $item->getBasename();
    }
    return $modules;
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

  /**
   * Makes sure necessary configuration has a UUID.
   */
  public function testModuleUuid() : void {
    foreach ($this->getSubModules() as $module) {
      $this->assertModuleUuids($module);
    }
  }

}
