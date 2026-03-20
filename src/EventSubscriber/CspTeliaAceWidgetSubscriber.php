<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\EventSubscriber;

/**
 * Event subscriber for CSP policy alteration.
 *
 * CSP directives for 'telia_ace_widget'-block and 'telia_ace_authenticated_widger'-block.
 *
 * @package Drupal\helfi_platform_config\EventSubscriber
 */
class CspTeliaAceWidgetSubscriber extends CspSubscriberBase {

  const CONNECT_SRC = [
    'https://hel.humany.net',
    'https://wds.ace.teliacompany.com',
    'https://chat.ace.teliacompany.net',
    'https://api.ace.teliacompany.net',
    'https://widgets.ace.teliacompany.net',
  ];
  const FONT_SRC = [
    'https://hel.humany.net',
    'https://ace-knowledge-cdn.teliacompany.net',
    'https://makasiini.hel.ninja',
    'https://widgets.ace.teliacompany.net',
  ];
  const FRAME_SRC = [
    'https://wds.ace.teliacompany.com',
    'https://widgets.ace.teliacompany.net',
  ];
  const IMG_SRC = [
    'https://hel.humany.net',
    'https://wds.ace.teliacompany.com',
    'https://widgets.ace.teliacompany.net',
  ];
  const SCRIPT_SRC = [
    'https://wds.ace.teliacompany.com',
    'https://widgets.ace.teliacompany.net',
  ];
  const STYLE_SRC = [
    'https://hel.humany.net',
    'https://wds.ace.teliacompany.com',
    'https://widgets.ace.teliacompany.net',
  ];

}
