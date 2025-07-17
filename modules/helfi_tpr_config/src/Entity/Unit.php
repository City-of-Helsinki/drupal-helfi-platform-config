<?php

declare(strict_types=1);

namespace Drupal\helfi_tpr_config\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\helfi_tpr\Entity\Unit as BaseUnit;

/**
 * Bundle class for tpr unit.
 */
class Unit extends BaseUnit {

  /**
   * Gets the picture uri.
   *
   * @return string|null
   *   The picture url.
   */
  public function getPictureUri() : ? string {
    /** @var \Drupal\media\MediaInterface $picture_url */
    $picture_url = $this->get('picture_url_override')->entity;

    if (!$picture_url) {
      $url = $this->get('picture_url')->value;

      // Run url through imagecache_external so that it is possible
      // to apply image styles later. This method is in a bundle class
      // so that helfi_tpr does not have to add dependency to
      // imagecache_external.
      if ($url) {
        return imagecache_external_generate_path($url) ?: NULL;
      }

      return NULL;
    }

    if ($file = $picture_url->get('field_media_image')->entity) {
      /** @var \Drupal\file\FileInterface $file */
      return $file->getFileUri();
    }

    return NULL;
  }

  /**
   * Gets the website URL.
   *
   * @return \Drupal\Core\Url|null
   *   The website url object.
   */
  public function getWebsiteUrl() : ? Url {
    $website_uri = $this->get('www')->getString();

    if ($website_uri) {
      return Url::fromUri($website_uri);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['unit_picture_caption'] = BaseFieldDefinition::create('string_long')
      ->setTranslatable(TRUE)
      ->setRevisionable(FALSE)
      ->setLabel(new TranslatableMarkup('Caption'))
      ->setDisplayOptions('form', [
        'weight' => 5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setSetting('allowed_formats', [0 => 'plain_text']);

    $fields['enrich_description'] = BaseFieldDefinition::create('text_with_summary')
      ->setTranslatable(TRUE)
      ->setRevisionable(FALSE)
      ->setLabel(new TranslatableMarkup('Long description (replacing missing information)'))
      ->setDescription(new TranslatableMarkup('Note! The content is displayed on the website only if the long description is missing from the data source.'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setSetting('allowed_formats', [0 => 'plain_text']);

    $fields['field_recommended_topics'] = BaseFieldDefinition::create('suggested_topics_reference')
      ->setName('field_recommended_topics')
      ->setLabel(new TranslatableMarkup('Automatically selected recommendation topics', [], ['context' => 'Recommendations']))
      ->setTargetEntityTypeId('tpr_service')
      ->setReadonly(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'suggested_topics_reference',
        'weight' => 1000,
        'module' => 'helfi_recommendations',
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    return $fields;
  }

}
