<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\EventSubscriber;

/**
 * Event subscriber for CSP policy alteration.
 *
 * CSP directives for 'react_and_share'-block.
 *
 * @package Drupal\helfi_platform_config\EventSubscriber
 */
class CspReactAndShareSubscriber extends CspSubscriberBase {

  const COMMON_DOMAINS = [
    'https://*.reactandshare.com',
    'https://*.askem.com',
  ];
  const CONNECT_SRC = self::COMMON_DOMAINS;
  const IMG_SRC = self::COMMON_DOMAINS;
  const SCRIPT_SRC = self::COMMON_DOMAINS;

}
