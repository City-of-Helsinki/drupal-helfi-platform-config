<?php

declare(strict_types=1);

namespace Drupal\hdbt_cookie_banner\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\hdbt_cookie_banner\Form\HdbtCookieBannerForm;

/**
 * HDBT cookie banner controller.
 */
class HdbtCookieBannerController extends ControllerBase {

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory.
   */
  public function __construct(private readonly ConfigFactoryInterface $config) {
  }

  /**
   * Returns site settings.
   */
  public function siteSettings(): CacheableJsonResponse {
    $config = $this->config->get(HdbtCookieBannerForm::SETTINGS);

    $response = new CacheableJsonResponse(Json::decode($config->get('site_settings')));
    $response->addCacheableDependency($config);
    $response->setMaxAge(600);
    $response->setPublic();

    return $response;
  }

}
