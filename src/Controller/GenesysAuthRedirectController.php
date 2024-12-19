<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Genesys auth redirect controller.
 */
class GenesysAuthRedirectController extends ControllerBase {

  /**
   * Returns a renderable array with attached JavaScript.
   */
  public function content() {
    $build = [
      '#markup' => $this->t('Redirecting...'),
      '#attached' => [
        'library' => ['helfi_platform_config/genesys_auth_redirect'],
      ],
    ];

    return $build;
  }

}
