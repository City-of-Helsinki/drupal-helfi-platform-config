<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\EventSubscriber;

/**
 * Event subscriber for CSP policy alteration.
 *
 * CSP directives for Social Media module.
 *
 * @package Drupal\helfi_platform_config\EventSubscriber
 */
class CspSocialMediaSubscriber extends CspSubscriberBase {

  const MODULE_DEPENDENCY = 'social_media';
  const CONNECT_SRC = [
    'https://connect.facebook.net',
  ];
  const SCRIPT_SRC = [
    'https://connect.facebook.net',
  ];

}
