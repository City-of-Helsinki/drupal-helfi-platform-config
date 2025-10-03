<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\EventSubscriber;

/**
 * Event subscriber for CSP policy alteration.
 *
 * CSP directives for 'ibm_chat_app'-block.
 *
 * @package Drupal\helfi_platform_config\EventSubscriber
 */
class CspIbmChatAppSubscriber extends CspSubscriberBase {

  const COMMON_DOMAINS = [
    'https://coh-chat-app-prod.ow6i4n9pdzm.eu-de.codeengine.appdomain.cloud',
    'https://coh-chat-app-test.mo1wrhhyog0.eu-de.codeengine.appdomain.cloud',
  ];
  const CONNECT_SRC = self::COMMON_DOMAINS;
  const FONT_SRC = self::COMMON_DOMAINS;
  const FRAME_SRC = [
    ...self::COMMON_DOMAINS,
    'https://coh-chat-app-ibm.eu-de.mybluemix.net',
    'https://coh-chat-app-prod-ibm.eu-de.mybluemix.net',
    'https://coh-chat-app-test.eu-de.mybluemix.net',
    'https://coh-chat-app-dev.eu-de.mybluemix.net',
    'https://coh-chat-app-prod.eu-de.mybluemix.net',
  ];
  const IMG_SRC = self::COMMON_DOMAINS;
  const SCRIPT_SRC = self::COMMON_DOMAINS;
  const STYLE_SRC = self::COMMON_DOMAINS;

}
