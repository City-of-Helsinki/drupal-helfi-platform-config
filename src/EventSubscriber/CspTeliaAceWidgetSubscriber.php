<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\EventSubscriber;

/**
 * Event subscriber for CSP policy alteration.
 *
 * CSP directives for 'telia_ace_widget'-block.
 *
 * @package Drupal\helfi_platform_config\EventSubscriber
 */
class CspTeliaAceWidgetSubscriber extends CspSubscriberBase {

  const CONNECT_SRC = [
    'https://hel.humany.net',
    'https://wds.ace.teliacompany.com',
    'https://chat.ace.teliacompany.net',
    'https://api.ace.teliacompany.net',
  ];
  const FONT_SRC = [
    'https://hel.humany.net',
    'https://ace-knowledge-cdn.teliacompany.net',
    'https://makasiini.hel.ninja',
  ];
  const FRAME_SRC = [
    'https://wds.ace.teliacompany.com',
  ];
  const IMG_SRC = [
    'https://hel.humany.net',
    'https://wds.ace.teliacompany.com',
  ];
  const SCRIPT_SRC = [
    'https://wds.ace.teliacompany.com',
  ];
  const STYLE_SRC = [
    'https://hel.humany.net',
    'https://wds.ace.teliacompany.com',
  ];

}
