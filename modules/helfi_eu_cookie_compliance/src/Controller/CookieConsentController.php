<?php

declare(strict_types = 1);

namespace Drupal\helfi_eu_cookie_compliance\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines CookieConsentController class.
 */
class CookieConsentController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $configFactory) {
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
    );
  }

  /**
   * Display the markup.
   *
   * @return array
   *   Return markup array.
   */
  public function content() : array {
    $settings = $this->configFactory->get('helfi_eu_cookie_compliance.cookie_consent_intro');
    $content = [];

    $content['#theme'] = 'cookie_consent_intro';
    $content['#title'] = $settings->get('cc.title');
    $content['#content'] = [
      '#type' => 'processed_text',
      '#text' => $settings->get('cc.content.value'),
      '#format' => $settings->get('cc.content.format'),
    ];
    return $content;
  }

}
