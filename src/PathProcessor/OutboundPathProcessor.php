<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\PathProcessor;

use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Symfony\Component\HttpFoundation\Request;
use Drupal\helfi_platform_config\Entity\ExternalEntity\MultisiteNode;

class OutboundPathProcessor implements OutboundPathProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], ?Request $request = NULL, ?BubbleableMetadata $bubbleable_metadata = NULL) {
    if (!empty($options['entity_type']) && $options['entity_type'] === 'helfi_multisite_node') {
      // if (empty($options['entity']) || !$options['entity'] instanceof MultisiteNode) {
      //   return $path;
      // }

      // $path = $options['entity']->getNodeUrl()->toString();
    }
    return $path;
  }

}
