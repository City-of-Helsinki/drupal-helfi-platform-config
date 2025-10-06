<?php

declare(strict_types=1);

namespace Drupal\helfi_media_remote_video\EventSubscriber;

use Drupal\helfi_platform_config\EventSubscriber\CspSubscriberBase;

/**
 * Event subscriber for CSP policy alteration.
 *
 * @package Drupal\helfi_media_remote_video\EventSubscriber
 */
class CspEventSubscriber extends CspSubscriberBase {

  const CONNECT_SRC = [
    'https://*.youtube-nocookie.com',
    'https://youtube-nocookie.com',
  ];
  const FRAME_SRC = [
    'https://*.youtube-nocookie.com',
    'https://youtube-nocookie.com',
    'https://*.youtube.com',
    'https://youtube.com',
    'https://*.youtu.be',
    'https://youtu.be',
    'https://*.vimeo.com',
    'https://vimeo.com',
    'https://*.icareus.com',
    'https://icareus.com',
    'https://*.helsinkikanava.fi',
  ];
  const OBJECT_SRC = [
    'https://*.youtube-nocookie.com',
    'https://youtube-nocookie.com',
    'https://*.youtube.com',
    'https://youtube.com',
    'https://*.youtu.be',
    'https://youtu.be',
    'https://*.vimeo.com',
    'https://vimeo.com',
    'https://*.icareus.com',
    'https://icareus.com',
    'https://*.helsinkikanava.fi',
  ];

}
