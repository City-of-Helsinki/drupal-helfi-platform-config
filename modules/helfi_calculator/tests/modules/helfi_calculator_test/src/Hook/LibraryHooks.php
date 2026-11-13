<?php

declare(strict_types=1);

namespace Drupal\helfi_calculator_test\Hook;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Exposes the helfi_calculator libraries under this test module.
 *
 * Installing helfi_calculator would also install helfi_base_content and its
 * many dependencies. Instead, this re-declares the helfi_calculator libraries
 * so that they point to the built files in helfi_calculator/dist.
 */
final class LibraryHooks {

  public function __construct(
    private readonly ModuleExtensionList $moduleExtensionList,
  ) {
  }

  /**
   * Implements hook_library_info_build().
   *
   * @return array<string, mixed>
   *   The library definitions.
   */
  #[Hook('library_info_build')]
  public function libraryInfoBuild(): array {
    $path = $this->moduleExtensionList->getPath('helfi_platform_config') . '/modules/helfi_calculator';
    $libraries = Yaml::decode((string) file_get_contents($path . '/helfi_calculator.libraries.yml'));

    foreach ($libraries as &$library) {
      unset($library['version']);

      $js = [];
      foreach ($library['js'] ?? [] as $file => $options) {
        $js['/' . $path . '/' . $file] = $options;
      }
      $library['js'] = $js;

      $library['dependencies'] = array_map(
        static fn (string $dependency) => str_replace('helfi_calculator/', 'helfi_calculator_test/', $dependency),
        $library['dependencies'] ?? [],
      );
    }

    return $libraries;
  }

}
