<?php

declare(strict_types=1);

namespace Drupal\hdbt_admin_tools;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;

/**
 * Service class for design selection related functions.
 */
class DesignSelectionManager {

  /**
   * Constructs a new DesignSelectionManager object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $fileUrlGenerator
   *   The file url generator service.
   */
  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
    protected FileUrlGeneratorInterface $fileUrlGenerator,
  ) {
  }

  /**
   * Get image paths based on available images.
   *
   * @param string $field_name
   *   Field name to be handled.
   * @param array $selections
   *   Array of field selections.
   *
   * @return array
   *   Returns an array of image paths.
   */
  public function getImages(string $field_name, array $selections): array {
    if (empty($field_name)) {
      return [];
    }

    $asset_path = $this->moduleHandler->getModule('hdbt_admin_tools')->getPath() . '/assets/images';
    $images = [];
    foreach ($selections as $selection) {
      $asset = "$asset_path/{$field_name}--$selection.svg";

      if (!file_exists(DRUPAL_ROOT . '/' . $asset)) {
        $asset = "$asset_path/custom-style.svg";
      }
      $images[$selection] = $this->fileUrlGenerator->generate($asset)->toString(TRUE)->getGeneratedUrl();
    }

    // Let modules to alter the image lists.
    $this->moduleHandler->alter('design_selection_images', $images, $field_name);

    return $images;
  }

}
