<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_contact_card_listing\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Error;
use Drupal\helfi_paragraphs_contact_card_listing\SocialMediaServiceParserTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'SocialMediaLink' formatter.
 */
#[FieldFormatter(
  id: 'helfi_social_media_link',
  label: new TranslatableMarkup('Social media link'),
  field_types: [
    'link',
  ],
)]
final class SocialMediaLinkFormatter extends FormatterBase {

  use SocialMediaServiceParserTrait;

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
    $plugin_definition,
  ) : self {
    $instance = parent::create($container, $configuration, $plugin_id,
      $plugin_definition);
    $instance->logger = $container->get('logger.factory')->get('helfi_paragraphs_contact_card_listing');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) : array {
    $element = [];

    foreach ($items as $delta => $item) {
      ['uri' => $uri] = $item->getValue();

      // Don't throw errors that would break media library views.
      try {
        $social_media_link = $this->processSocialMediaDomain($uri);
      }
      catch (\InvalidArgumentException $e) {
        Error::logException($this->logger, $e);
        continue;
      }

      $element[$delta] = [
        '#theme' => 'helfi_social_media_link',
        '#social_media_link' => $social_media_link,
      ];
    }

    return $element;
  }

}
