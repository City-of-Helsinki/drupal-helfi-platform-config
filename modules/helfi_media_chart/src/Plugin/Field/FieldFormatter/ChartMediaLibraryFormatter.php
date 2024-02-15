<?php

declare(strict_types=1);

namespace Drupal\helfi_media_chart\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Utility\Error;
use Drupal\helfi_media_chart\UrlParserTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'Chart' formatter.
 *
 * @FieldFormatter(
 *   id = "helfi_chart_media_library",
 *   label = @Translation("Chart - Media library"),
 *   field_types = {
 *     "link",
 *   }
 * )
 */
final class ChartMediaLibraryFormatter extends FormatterBase {

  use UrlParserTrait;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private LoggerInterface $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) : self {
    $instance = parent::create($container, $configuration, $plugin_id,
      $plugin_definition);
    $instance->logger = $container->get('logger.factory')->get('helfi_chart');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) :array {
    $element = [];

    foreach ($items as $delta => $item) {
      ['uri' => $uri] = $item->getValue();

      try {
        $url = $this->mediaUrlToUri($uri);
      }
      catch (\InvalidArgumentException $e) {
        Error::logException($this->logger, $e);
        continue;
      }

      $element[$delta] = [
        '#theme' => 'chart_iframe__media_library',
        '#url' => (string) $url,
      ];
    }

    return $element;
  }

}
