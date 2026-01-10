<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Entity\ExternalEntity;

use Drupal\external_entities\Entity\ExternalEntity;
use Drupal\Core\Url;

/**
 * A bundle class for Helfi: multisite node external entities.
 */
final class MultisiteNode extends ExternalEntity {

  /**
   * Get the external URL of the multisite node.
   *
   * @return \Drupal\Core\Url
   *   The external URL.
   */
  public function getExternalUrl() {
    $url = $this->get('node_url')->value;
    return Url::fromUri($url);
  }

}
