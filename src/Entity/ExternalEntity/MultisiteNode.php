<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Entity\ExternalEntity;

use Drupal\external_entities\Entity\ExternalEntity;
use Drupal\Core\Url;

/**
 * A bundle class for Helfi: multisite node external entities.
 */
final class MultisiteNode extends ExternalEntity {

  public function toUrl($rel = NULL, array $options = []) {
    return parent::toUrl($rel, $options);

    // if ($options['path_processing'] === FALSE) {
    //   return parent::toUrl($rel, $options);
    // }
    
    $url = $this->get('node_url')->value;
    return Url::fromUri($url);
  }

  public function getExternalUrl() {
    $url = $this->get('node_url')->value;
    return Url::fromUri($url);
  }

}
