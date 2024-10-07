<?php

declare(strict_types=1);

namespace Drupal\hdbt_cookie_banner\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\hdbt_cookie_banner\Form\HdbtCookieBannerForm;

/**
 * Defines HdbtCookiePolicyController class.
 */
final class HdbtCookiePolicyController extends ControllerBase {

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory.
   */
  public function __construct(private readonly ConfigFactoryInterface $config) {
  }

  /**
   * Display the cookie information.
   *
   * @return array
   *   Return markup array.
   */
  public function content(): array {
    $config = $this->config->get(HdbtCookieBannerForm::SETTINGS);
    $content = [];

    $content['#theme'] = 'cookie_policy';
    $content['#title'] = $config->get('cookie_information.title');
    $content['#content'] = $config->get('cookie_information.content');

    return $content;
  }

}
