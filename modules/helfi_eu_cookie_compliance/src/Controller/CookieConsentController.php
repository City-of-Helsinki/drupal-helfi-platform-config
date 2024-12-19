<?php

declare(strict_types=1);

namespace Drupal\helfi_eu_cookie_compliance\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines CookieConsentController class.
 */
final class CookieConsentController extends ControllerBase {

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) : self {
    return new self(
      $container->get('config.factory'),
    );
  }

  /**
   * Display the markup.
   *
   * @return array
   *   Return markup array.
   */
  public function content() {
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
