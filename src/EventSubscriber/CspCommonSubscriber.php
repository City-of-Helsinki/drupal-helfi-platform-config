<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\EventSubscriber;

/**
 * Event subscriber for CSP policy alteration.
 *
 * Common CSP directives.
 *
 * @package Drupal\helfi_platform_config\EventSubscriber
 */
class CspCommonSubscriber extends CspSubscriberBase {

  const CONNECT_SRC = [
    'https://*.hel.fi',
    'https://unpkg.com',
  ];
  const FONT_SRC = [
    'https://*.hel.fi',
  ];
  const FRAME_SRC = [
    'https://*.hel.fi',
    'https://*.userneeds.com',
    'https://agreeable-island-03e85b803.azurestaticapps.net',
    'https://*.hotjar.com',
    'https://*.facebook.com',
    'https://*.twitter.com',
    'https://*.linkedin.com',
    'https://*.readspeaker.com',
    'https://*.google.com',
    'https://*.snoobi.com',
    'https://*.dreambroker.com',
    'https://dreambroker.com',
    'https://pollev.com',
    'https://tyoterveys-helsinki-pv.mail-eur.net',
    'https://walls.io',
    'https://*.flockler.com',
    'https://*.lightwidget.com',
    'https://hel-thk-botti.kuurahealth.com',
    'https://*.giosg.com',
    'https://*.giosgusercontent.com',
    'https://helfi.fi1.frosmo.com',
    'https://survey.feedbackly.com',
    'https://hkp.maanmittauslaitos.fi',
    'https://reittiopas.hsl.fi',
  ];
  const IMG_SRC = [
    'data:',
  ];
  const MEDIA_SRC = [
    'data:',
  ];
  const SCRIPT_SRC = [
    'blob:',
    'https://*.hel.fi',
  ];
  const STYLE_SRC = [
    'https://*.hel.fi',
  ];

}
