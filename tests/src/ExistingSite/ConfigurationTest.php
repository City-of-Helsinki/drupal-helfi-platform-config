<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\ExistingSite;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Scans bundled configuration.
 *
 * @group helfi_platform_config
 */
class ConfigurationTest extends ExistingSiteBase {

  /**
   * The extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  private ModuleExtensionList $extensionList;

  /**
   * The typed config manager.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface
   */
  private TypedConfigManagerInterface $typedConfigManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $this->extensionList = $this->container->get('extension.list.module');
    $this->typedConfigManager = $this->container->get('config.typed');
  }

  /**
   * Installs all modules one by one.
   */
  #[RunInSeparateProcess]
  #[DataProvider('getSubModules')]
  public function testModuleInstallation(string $module) : void {
    // Suppress risky test warning if no exception was thrown.
    // These are run as separate tests, so each installation is isolated
    // and gets to use all available memory. Installing all modules in a
    // test causes OOM errors.
    // This is an ExistingSite test, so all tests that are run after
    // this one have all the modules installed.
    $this->expectNotToPerformAssertions();

    /** @var \Drupal\Core\Extension\ModuleInstallerInterface $moduleInstaller */
    $moduleInstaller = $this->container->get('module_installer');
    $moduleInstaller->install([$module]);
  }

  /**
   * Gets the submodules.
   *
   * @return array
   *   The submodules list.
   */
  public static function getSubModules() : array {
    // Get module path without Drupal services since data providers run before
    // Drupal is bootstrapped. Go up 3 directories from the test file location.
    $path = dirname(__DIR__, 3);

    $modules = [];
    /** @var \DirectoryIterator $item */
    foreach (new \DirectoryIterator($path . '/modules') as $item) {
      if (!$item->isDir() || $item->isDot()) {
        continue;
      }
      $modules[] = $item->getBasename();
    }

    return array_map(static fn ($module) => [$module], $modules);
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

      if (!is_dir($configPath)) {
        continue;
      }

      /** @var \DirectoryIterator $file */
      foreach (new \DirectoryIterator($configPath) as $file) {
        if ($file->getExtension() !== 'yml') {
          continue;
        }

        try {
          $configFileName = str_replace('.yml', '', $file->getFilename());
          $definition = $this->typedConfigManager->get($configFileName)
            ->getDataDefinition();
        }
        catch (\InvalidArgumentException $e) {
          // Support optional configuration that are not installed on every
          // instance. This test does not catch missing uuids on these configs.
          if ($type === 'optional') {
            continue;
          }

          throw $e;
        }

        // Skip configuration that doesn't require UUID.
        if (!isset($definition['mapping']['uuid'])) {
          continue;
        }
        $yaml = Yaml::decode(file_get_contents($file->getPathname()));
        $this->assertArrayHasKey('uuid', $yaml, "[{$file->getPathname()}] is missing UUID");
      }
    }
  }

  /**
   * Asserts module configuration.
   */
  #[Depends('testModuleInstallation')]
  public function testModuleConfiguration() : void {
    foreach (self::getSubModules() as [$module]) {
      $this->assertModuleUuids($module);
    }
  }

  /**
   * Makes sure configuration does not contain '_core' key.
   */
  #[Depends('testModuleInstallation')]
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
