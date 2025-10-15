<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\EventSubscriber;

/**
 * Event subscriber for CSP policy alteration.
 *
 * CSP directives for Siteimprove module.
 *
 * @package Drupal\helfi_platform_config\EventSubscriber
 */
class CspSiteimproveSubscriber extends CspSubscriberBase {

  const CONNECT_SRC = [
    'https://*.siteimprove.com',
  ];
  const FRAME_SRC = [
    'https://*.siteimprove.com',
    'https://*.siteimproveanalytics.com',
  ];

}
