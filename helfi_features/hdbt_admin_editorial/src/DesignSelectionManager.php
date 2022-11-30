<?php

declare(strict_types = 1);

namespace Drupal\hdbt_admin_editorial;

use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Service class for design selection related functions.
 */
class DesignSelectionManager {

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * Constructs a new DesignSelectionManager object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
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
  public function getImages(string $field_name, array $selections) : array {
    if (empty($field_name)) {
      return [];
    }

    $asset_path = $this->moduleHandler->getModule('hdbt_admin_editorial')->getPath() . '/assets/images';
    $images = [];
    /** @var \Drupal\Core\File\FileUrlGeneratorInterface $service */
    $service = \Drupal::service('file_url_generator');

    foreach ($selections as $selection) {
      $asset = "$asset_path/{$field_name}--$selection.svg";

      if (!file_exists(DRUPAL_ROOT . '/' . $asset)) {
        $asset = "$asset_path/custom-style.svg";
      }
      $images[$selection] = $service->generate($asset)->toString(TRUE)->getGeneratedUrl();
    }

    // Let modules to alter the image lists.
    $this->moduleHandler->alter('hdbt_admin_editorial_design_selection_images', $images, $field_name);

    return $images;
  }

}
