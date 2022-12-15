<?php

declare(strict_types = 1);

namespace Drupal\helfi_media_map\Plugin\media\Source;

use Drupal\media\MediaSourceBase;
use Drupal\media\MediaTypeInterface;

/**
 * Map entity media source.
 *
 * @MediaSource(
 *   id = "hel_map",
 *   label = @Translation("Map - palvelukartta.hel.fi and kartta.hel.fi"),
 *   allowed_field_types = {"link"},
 *   description = @Translation("Provides business logic and metadata for Helsinki maps."),
 *   forms = {
 *     "media_library_add" = "Drupal\helfi_media_map\Form\HelfiMediaMapAddForm"
 *   }
 * )
 */
final class Map extends MediaSourceBase {

  public const PALVELUKARTTA_URL = 'palvelukartta.hel.fi';
  public const KARTTA_URL = 'kartta.hel.fi';

  /**
   * List of valid map base urls.
   */
  public const VALID_URLS = [
    'palvelukartta' => self::PALVELUKARTTA_URL,
    'kartta' => self::KARTTA_URL,
  ];

  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes() : array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function createSourceField(MediaTypeInterface $type) {
    $storage = $this->getSourceFieldStorage() ?: $this->createSourceFieldStorage();
    return $this->entityTypeManager
      ->getStorage('field_config')
      ->create([
        'field_storage' => $storage,
        'bundle' => $type->id(),
        'label' => 'Url',
        'required' => TRUE,
      ]);
  }

}
