<?php

declare(strict_types=1);

namespace Drupal\helfi_media_chart\EventSubscriber;

use Drupal\helfi_platform_config\EventSubscriber\CspSubscriberBase;

/**
 * Event subscriber for CSP policy alteration.
 *
 * @package Drupal\helfi_media_chart\EventSubscriber
 */
class CspEventSubscriber extends CspSubscriberBase {

  const FRAME_SRC = [
    'https://*.powerbi.com',
  ];
  const OBJECT_SRC = [
    'https://*.powerbi.com',
  ];

}
