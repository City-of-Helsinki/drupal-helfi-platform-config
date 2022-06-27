<?php

declare(strict_types = 1);

namespace Drupal\helfi_news_feed\Plugin\ExternalEntities\StorageClient;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\external_entities\ResponseDecoder\ResponseDecoderFactoryInterface;
use Drupal\helfi_news_feed\HelfiExternalEntityBase;

/**
 * External entity storage client for News groups taxonomy terms.
 *
 * @ExternalEntityStorageClient(
 *   id = "helfi_news_groups",
 *   label = @Translation("Helfi: News groups"),
 *   description = @Translation("Retrieves news groups taxonomy terms from Helfi")
 * )
 */
final class NewsGroups extends HelfiExternalEntityBase {

  /**
   * Query parameters.
   *
   * @var array|string[]
   */
  protected array $query = [
    'fields[taxonomy_term--news_groups]' => 'id,name,changed,langcode,status',
  ];

  /**
   * Json api endpoint for taxonomy term.
   *
   * @var string
   */
  protected string $endpoint = '/jsonapi/taxonomy_term/news_group';

}
