<?php

declare(strict_types = 1);

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
 *   id = "helfi_chart",
 *   label = @Translation("Chart"),
 *   field_types = {
 *     "link",
 *   }
 * )
 */
final class ChartFormatter extends FormatterBase {

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
    $entity = $items->getEntity();

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
        '#theme' => 'chart_iframe',
        '#title' => $entity->get('field_helfi_chart_title')->value,
        '#url' => (string) $url,
        '#domain' => $url->getHost(),
        '#attached' => [
          'library' => [
            'helfi_media_chart/helfi_charts',
          ],
        ],
      ];
    }

    return $element;
  }

}
