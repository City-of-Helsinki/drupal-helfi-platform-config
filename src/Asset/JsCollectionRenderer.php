<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Asset;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Asset\AssetQueryStringInterface;
use Drupal\Core\Asset\JsCollectionRenderer as BaseJsCollectionRenderer;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

/**
 * Renders JavaScript assets.
 */
#[AsDecorator(decorates: 'asset.js.collection_renderer')]
class JsCollectionRenderer extends BaseJsCollectionRenderer {

  /**
   * Constructs a JsCollectionRenderer.
   */
  public function __construct(
    AssetQueryStringInterface $assetQueryString,
    FileUrlGeneratorInterface $fileUrlGenerator,
    TimeInterface $time,
    private JsIntegrityBuilder $integrityBuilder,
    private JsCspHashCollector $hashCollector,
    private ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct($assetQueryString, $fileUrlGenerator, $time);
  }

  /**
   * {@inheritdoc}
   *
   * Adds SRI integrity metadata and collects matching CSP hashes for file
   * assets. CSP Level 3 allows hash sources to match external scripts when the
   * script element's integrity metadata is listed in the policy.
   *
   * @see https://www.w3.org/TR/CSP3/#external-hash
   */
  public function render(array $js_assets) {
    if (!$this->isExternalScriptHashesEnabled()) {
      return parent::render($js_assets);
    }

    $elements = parent::render($js_assets);

    foreach ($elements as &$element) {
      $src = $element['#attributes']['src'] ?? '';
      if ($src === '') {
        continue;
      }

      $integrity = $this->integrityBuilder->getIntegrityForScriptUrl($src);
      if ($integrity === NULL) {
        continue;
      }

      $element['#attributes']['integrity'] = $integrity;
      $element['#attributes']['crossorigin'] = 'anonymous';
      $this->hashCollector->addScriptHash($integrity);
    }

    return $elements;
  }

  /**
   * Check if external script hashes are enabled.
   */
  private function isExternalScriptHashesEnabled(): bool {
    return (bool) $this->configFactory
      ->get('helfi_platform_config.csp')
      ->get('external_script_hashes');
  }

}
