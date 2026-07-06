<?php

declare(strict_types=1);

namespace Drupal\helfi_llms_txt\Controller;

use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;

/**
 * Serves the /llms.txt path.
 */
final readonly class LlmsTxtController implements ContainerInjectionInterface {

  use AutowireTrait;

  public function __construct(
    private ConfigFactoryInterface $configFactory,
  ) {
  }

  /**
   * Returns the llms.txt content.
   */
  public function __invoke(): CacheableResponse {
    $config = $this->configFactory->get('helfi_llms_txt.settings');

    $response = new CacheableResponse($config->get('content'), headers: [
      'Content-Type' => 'text/markdown; charset=utf-8',
    ]);

    $response->addCacheableDependency($config);

    return $response;
  }

}
