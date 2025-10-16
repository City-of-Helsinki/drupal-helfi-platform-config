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
  const SCRIPT_SRC = [
    'https://siteimprove.com',
    'https://*.siteimprove.com',
    'https://siteimproveanalytics.com',
    'https://*.siteimproveanalytics.com',
  ];
  const FRAME_SRC = [
    'https://*.siteimprove.com',
    'https://*.siteimproveanalytics.com',
  ];

}
